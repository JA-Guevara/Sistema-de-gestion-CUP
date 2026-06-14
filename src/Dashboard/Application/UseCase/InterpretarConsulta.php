<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

use App\Dashboard\Infrastructure\Persistence\ReporteRepository;
use App\Gestion\Domain\Entity\Gestion;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Asistente de IA: convierte una consulta en lenguaje natural en filtros para
 * el tablero de reportes, usando la API de Claude (Anthropic) con salida
 * estructurada (JSON Schema). Se apoya en el catálogo real de la gestión
 * (carreras/materias/docentes) para mapear nombres → ids, y valida la
 * respuesta del modelo contra ese catálogo antes de devolverla.
 *
 * No usa el SDK oficial a propósito (evita tocar composer.json): habla con la
 * API REST vía symfony/http-client, que ya es dependencia del proyecto.
 */
final readonly class InterpretarConsulta
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    // Modelo por defecto. Para respuestas más rápidas y baratas (suficiente
    // para esta tarea) poné ANTHROPIC_MODEL=claude-haiku-4-5 en .env.local.
    private const MODELO_DEFECTO = 'claude-opus-4-8';

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

        $apiKey = (string) ($_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: '');
        if ($apiKey === '') {
            return $this->fallback('El asistente todavía no está configurado (falta la clave ANTHROPIC_API_KEY).');
        }
        $modelo = (string) ($_ENV['ANTHROPIC_MODEL'] ?? getenv('ANTHROPIC_MODEL') ?: self::MODELO_DEFECTO);

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
            - vista: "lista" si pide la lista/detalle/exportar o buscar un postulante; "reportes" si pide gráficos/indicadores/resumen; "ninguna" si no queda claro.
            - texto: término de búsqueda libre (un nombre o CI) si lo menciona; si no, "".
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
                'texto' => ['type' => 'string'],
                'respuesta' => ['type' => 'string'],
            ],
            'required' => ['vista', 'carrera', 'materia', 'docente', 'estado', 'texto', 'respuesta'],
        ];

        try {
            $response = $this->http->request('POST', self::ENDPOINT, [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $modelo,
                    'max_tokens' => 400,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                    'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
                ],
                'timeout' => 20,
            ]);
            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);
        } catch (\Throwable) {
            return $this->fallback('No pude consultar al asistente en este momento. Probá de nuevo.');
        }

        $textoModelo = '';
        foreach (($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $textoModelo = (string) ($block['text'] ?? '');
                break;
            }
        }

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
        $vista = (string) ($parsed['vista'] ?? 'ninguna');

        return [
            'ok' => true,
            'vista' => in_array($vista, ['reportes', 'lista', 'ninguna'], true) ? $vista : 'ninguna',
            'carrera' => in_array($carrera, $idsCarrera, true) ? $carrera : 0,
            'materia' => in_array($materia, $idsMateria, true) ? $materia : 0,
            'docente' => in_array($docente, $idsDocente, true) ? $docente : 0,
            'estado' => in_array($estado, ['APROBADO', 'REPROBADO', 'INCOMPLETO'], true) ? $estado : '',
            'texto' => trim((string) ($parsed['texto'] ?? '')),
            'respuesta' => trim((string) ($parsed['respuesta'] ?? '')) ?: 'Listo.',
        ];
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
            'texto' => '',
            'respuesta' => $mensaje,
        ];
    }
}
