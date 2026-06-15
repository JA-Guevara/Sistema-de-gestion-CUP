<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

use App\Dashboard\Infrastructure\Persistence\ReporteRepository;
use App\Gestion\Domain\Entity\Gestion;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Asistente de IA: convierte una consulta en lenguaje natural en filtros para
 * el tablero de reportes, usando la API de OpenAI con salida
 * estructurada (JSON Schema). Se apoya en el catálogo real de la gestión
 * (carreras/materias/docentes) para mapear nombres → ids, y valida la
 * respuesta del modelo contra ese catálogo antes de devolverla.
 *
 * No usa el SDK oficial a propósito (evita tocar composer.json): habla con la
 * API REST vía symfony/http-client, que ya es dependencia del proyecto.
 */
final readonly class InterpretarConsulta
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    // Modelo por defecto. Para respuestas más rápidas y económicas podés usar
    // OPENAI_MODEL=gpt-4o-mini en .env.local.
    private const MODELO_DEFECTO = 'gpt-4o-mini';
    /** @var list<string> */
    private const ESTADOS_POSTULACION = ['BORRADOR', 'PRESENTADA', 'VALIDADA', 'CONFIRMADA', 'RECHAZADA', 'ANULADA', 'PENDIENTE', 'COMPLETADA'];

    public function __construct(
        private ReporteRepository $repo,
        private HttpClientInterface $http,
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(Gestion $gestion, string $consulta): array
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return $this->fallback('Escribí o decí qué reporte querés ver.');
        }

        $apiKey = (string) ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '');
        if ($apiKey === '') {
            return $this->fallback('El asistente todavía no está configurado (falta la clave OPENAI_API_KEY).');
        }
        $modelo = (string) ($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: self::MODELO_DEFECTO);

        $gestionId = (int) $gestion->id;
        $catalogo = [
            'carreras' => array_map(
                static fn (array $c): array => ['id' => (int) $c['id'], 'nombre' => (string) $c['nombre']],
                $this->repo->carrerasConEstudiantes($gestionId)
            ),
            'materias' => array_map(
                static fn (array $m): array => ['id' => (int) $m['id'], 'nombre' => (string) $m['nombre']],
                $this->repo->materiasConNotas($gestionId)
            ),
            'docentes' => array_map(
                static fn (array $d): array => ['id' => (int) $d['id'], 'nombre' => trim(((string) $d['lastName']) . ' ' . ((string) $d['firstName']))],
                $this->repo->docentesDeGestion($gestionId)
            ),
        ];

        $system = <<<TXT
            Sos el asistente de reportes del CUP (curso preuniversitario de la FICCT). Convertís la frase del usuario en filtros para un tablero. Respondés SOLO con los campos del esquema, sin texto extra.
            Reglas:
            - carrera, materia, docente: devolvé el id EXACTO del catálogo cuyo nombre coincida (aceptá errores de tipeo, mayúsculas y coincidencias parciales). Si no se menciona o no hay coincidencia clara, devolvé 0.
            - estado: "APROBADO" si dice aprobado/aprobados/pasaron; "REPROBADO" si reprobado/aplazado/no pasaron; "INCOMPLETO" si incompleto/sin notas; "" si no corresponde.
            - estadoPostulacion: "CONFIRMADA", "VALIDADA", "PRESENTADA", "BORRADOR", "RECHAZADA", "ANULADA", "PENDIENTE" o "COMPLETADA" cuando hablen del estado administrativo de la postulacion; "" si no aplica.
            - soloOficiales: true si piden estudiantes oficiales, admitidos, aceptados, confirmados o validados; false en otro caso.
            - soloAsignados: true si piden solo estudiantes con materias asignadas/inscritas; false en otro caso.
            - soloSinAsignados: true si piden estudiantes sin materias asignadas/no inscritos en materias; false en otro caso.
            - vista: "lista" si pide la lista/detalle/exportar o buscar un postulante; "reportes" si pide gráficos/indicadores/resumen; "ninguna" si no queda claro.
            - Si preguntan por postulantes totales, aceptados, rechazados, presentados o métricas de docentes, priorizá vista="reportes" y no fuerces estado académico.
            - texto: término de búsqueda libre (un nombre o CI) si lo menciona; si no, "".
            - El dashboard también incluye métricas de postulaciones (totales, aceptados, rechazados, presentados y docentes), pero la salida SIEMPRE debe respetar este esquema de filtros.
            - respuesta: una sola frase corta, amable, en español, confirmando qué filtraste.
            TXT;

        $user = "Catálogo (usá estos ids):\n"
            . json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nConsulta del usuario:\n" . $consulta;

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'vista' => ['type' => 'string', 'enum' => ['reportes', 'lista', 'ninguna']],
                'carrera' => ['type' => 'integer'],
                'materia' => ['type' => 'integer'],
                'docente' => ['type' => 'integer'],
                'estado' => ['type' => 'string', 'enum' => ['', 'APROBADO', 'REPROBADO', 'INCOMPLETO']],
                'estadoPostulacion' => ['type' => 'string', 'enum' => ['', 'BORRADOR', 'PRESENTADA', 'VALIDADA', 'CONFIRMADA', 'RECHAZADA', 'ANULADA', 'PENDIENTE', 'COMPLETADA']],
                'soloOficiales' => ['type' => 'boolean'],
                'soloAsignados' => ['type' => 'boolean'],
                'soloSinAsignados' => ['type' => 'boolean'],
                'texto' => ['type' => 'string'],
                'respuesta' => ['type' => 'string'],
            ],
            'required' => ['vista', 'carrera', 'materia', 'docente', 'estado', 'estadoPostulacion', 'soloOficiales', 'soloAsignados', 'soloSinAsignados', 'texto', 'respuesta'],
        ];

        try {
            $response = $this->http->request('POST', self::ENDPOINT, [
                'headers' => [
                    'authorization' => 'Bearer ' . $apiKey,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $modelo,
                    'temperature' => 0,
                    'max_tokens' => 400,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'dashboard_filters',
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                ],
                'timeout' => 20,
            ]);
            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);
        } catch (\Throwable) {
            return $this->fallback('No pude consultar al asistente en este momento. Probá de nuevo.');
        }

        $textoModelo = (string) (($data['choices'][0]['message']['content'] ?? ''));

        $parsed = json_decode($textoModelo, true);
        if (!is_array($parsed)) {
            return $this->fallback('No entendí bien la consulta. ¿La podés reformular?');
        }

        $idsCarrera = array_column($catalogo['carreras'], 'id');
        $idsMateria = array_column($catalogo['materias'], 'id');
        $idsDocente = array_column($catalogo['docentes'], 'id');

        $carrera = (int) ($parsed['carrera'] ?? 0);
        $materia = (int) ($parsed['materia'] ?? 0);
        $docente = (int) ($parsed['docente'] ?? 0);
        $estado = (string) ($parsed['estado'] ?? '');
        $estadoPostulacion = (string) ($parsed['estadoPostulacion'] ?? '');
        $soloOficiales = (bool) ($parsed['soloOficiales'] ?? false);
        $soloAsignados = (bool) ($parsed['soloAsignados'] ?? false);
        $soloSinAsignados = (bool) ($parsed['soloSinAsignados'] ?? false);
        $vista = (string) ($parsed['vista'] ?? 'ninguna');

        $inferidos = $this->inferirFlags($consulta);
        if (!$soloOficiales) {
            $soloOficiales = $inferidos['soloOficiales'];
        }
        if (!$soloAsignados) {
            $soloAsignados = $inferidos['soloAsignados'];
        }
        if (!$soloSinAsignados) {
            $soloSinAsignados = $inferidos['soloSinAsignados'];
        }
        if ($estadoPostulacion === '') {
            $estadoPostulacion = $inferidos['estadoPostulacion'];
        }

        if ($soloSinAsignados) {
            $soloAsignados = false;
        }

        $vista = in_array($vista, ['reportes', 'lista', 'ninguna'], true) ? $vista : 'ninguna';
        $estadoPostulacion = in_array($estadoPostulacion, self::ESTADOS_POSTULACION, true) ? $estadoPostulacion : '';
        $tieneFiltros = $carrera > 0 || $materia > 0 || $docente > 0 || $estado !== '' || $estadoPostulacion !== '' || $soloOficiales || $soloAsignados || $soloSinAsignados || trim((string) ($parsed['texto'] ?? '')) !== '';
        if ($vista === 'ninguna') {
            $vista = $this->inferirVista($consulta, $tieneFiltros);
        }

        if (($soloOficiales || $soloAsignados || $soloSinAsignados || $estadoPostulacion !== '') && $vista === 'reportes') {
            $vista = 'lista';
        }

        $respuestaModelo = trim((string) ($parsed['respuesta'] ?? ''));
        $respuesta = $this->normalizarRespuesta($respuestaModelo, $vista, $tieneFiltros);

        return [
            'ok' => true,
            'vista' => $vista,
            'carrera' => in_array($carrera, $idsCarrera, true) ? $carrera : 0,
            'materia' => in_array($materia, $idsMateria, true) ? $materia : 0,
            'docente' => in_array($docente, $idsDocente, true) ? $docente : 0,
            'estado' => in_array($estado, ['APROBADO', 'REPROBADO', 'INCOMPLETO'], true) ? $estado : '',
            'estadoPostulacion' => $estadoPostulacion,
            'soloOficiales' => $soloOficiales,
            'soloAsignados' => $soloAsignados,
            'soloSinAsignados' => $soloSinAsignados,
            'texto' => trim((string) ($parsed['texto'] ?? '')),
            'respuesta' => $respuesta,
        ];
    }

    /** @return array{soloOficiales: bool, soloAsignados: bool, soloSinAsignados: bool, estadoPostulacion: string} */
    private function inferirFlags(string $consulta): array
    {
        $q = mb_strtolower($consulta);

        $soloOficiales = false;
        foreach (['oficial', 'oficiales', 'admitidos', 'aceptados', 'confirmados', 'validados', 'estudiantes oficiales'] as $k) {
            if (str_contains($q, $k)) {
                $soloOficiales = true;
                break;
            }
        }

        $soloAsignados = false;
        foreach (['materia asignada', 'materias asignadas', 'con materias', 'inscritas', 'inscritos en materias', 'asignada'] as $k) {
            if (str_contains($q, $k)) {
                $soloAsignados = true;
                break;
            }
        }

        $soloSinAsignados = false;
        foreach (['sin materias', 'sin materia', 'sin asignacion', 'sin asignación', 'no asignados', 'no tienen materias'] as $k) {
            if (str_contains($q, $k)) {
                $soloSinAsignados = true;
                break;
            }
        }

        $estadoPostulacion = '';
        if (str_contains($q, 'rechazad')) {
            $estadoPostulacion = 'RECHAZADA';
        } elseif (str_contains($q, 'anulad')) {
            $estadoPostulacion = 'ANULADA';
        } elseif (str_contains($q, 'borrador')) {
            $estadoPostulacion = 'BORRADOR';
        } elseif (str_contains($q, 'presentad')) {
            $estadoPostulacion = 'PRESENTADA';
        } elseif (str_contains($q, 'pendiente')) {
            $estadoPostulacion = 'PENDIENTE';
        } elseif (str_contains($q, 'confirmad') || str_contains($q, 'admitid') || str_contains($q, 'aceptad')) {
            $estadoPostulacion = 'CONFIRMADA';
        } elseif (str_contains($q, 'validad')) {
            $estadoPostulacion = 'VALIDADA';
        } elseif (str_contains($q, 'completad')) {
            $estadoPostulacion = 'COMPLETADA';
        }

        return [
            'soloOficiales' => $soloOficiales,
            'soloAsignados' => $soloAsignados,
            'soloSinAsignados' => $soloSinAsignados,
            'estadoPostulacion' => $estadoPostulacion,
        ];
    }

    private function inferirVista(string $consulta, bool $tieneFiltros): string
    {
        if ($tieneFiltros) {
            return 'lista';
        }

        $q = mb_strtolower($consulta);
        $clavesLista = ['lista', 'detalle', 'buscar', 'ci', 'nombre', 'postulante'];
        foreach ($clavesLista as $k) {
            if (str_contains($q, $k)) {
                return 'lista';
            }
        }

        $clavesReportes = ['promedio', 'promedios', 'materia', 'gráfico', 'grafico', 'indicador', 'kpi', 'reporte', 'aprobados', 'reprobados', 'postulaciones', 'aceptados', 'rechazados'];
        foreach ($clavesReportes as $k) {
            if (str_contains($q, $k)) {
                return 'reportes';
            }
        }

        return 'reportes';
    }

    private function normalizarRespuesta(string $respuestaModelo, string $vista, bool $tieneFiltros): string
    {
        if ($respuestaModelo === '') {
            return $this->respuestaPorDefecto($vista, $tieneFiltros);
        }

        $r = mb_strtolower($respuestaModelo);
        $genericas = [
            'no se encontraron filtros específicos',
            'no se encontraron filtros especificos',
            'sin filtros',
            'no encontré filtros',
            'no encontre filtros',
            'no pude filtrar la consulta',
            'no se pudo filtrar la consulta',
            'ya que no es clara',
        ];

        foreach ($genericas as $g) {
            if (str_contains($r, $g)) {
                return $this->respuestaPorDefecto($vista, $tieneFiltros);
            }
        }

        return $respuestaModelo;
    }

    private function respuestaPorDefecto(string $vista, bool $tieneFiltros): string
    {
        if ($tieneFiltros) {
            return 'Listo, apliqué los filtros que entendí. Si querés, lo afino por carrera, materia, docente, estado o tipo de postulantes.';
        }

        if ($vista === 'lista') {
            return 'Te llevo a la vista de detalle. Decime CI, nombre o estado para filtrar más fino.';
        }

        return 'Te muestro los reportes generales. Si querés, indicame carrera, materia o docente para filtrar.';
    }

    /** @return array<string, mixed> */
    private function fallback(string $mensaje): array
    {
        return [
            'ok' => false,
            'vista' => 'ninguna',
            'carrera' => 0,
            'materia' => 0,
            'docente' => 0,
            'estado' => '',
            'estadoPostulacion' => '',
            'soloOficiales' => false,
            'soloAsignados' => false,
            'soloSinAsignados' => false,
            'texto' => '',
            'respuesta' => $mensaje,
        ];
    }
}
