<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Asistente de AYUDA global: responde en lenguaje natural "como funciona / donde
 * esta X" sobre el sistema CUP. Usa la MISMA API (OpenAI via symfony/http-client)
 * que el asistente de reportes, pero con un prompt de onboarding y un catalogo de
 * modulos embebido (grounding) para no inventar funciones. Devuelve texto plano.
 */
final readonly class AsistenteAyuda
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const MODELO_DEFECTO = 'gpt-4o-mini';

    private const SYSTEM_PROMPT = <<<'TXT'
        Sos el asistente de ayuda del sistema CUP (Curso Preuniversitario) de la FICCT - UAGRM.
        Tu UNICO trabajo es explicar, en español claro y breve, COMO USAR el sistema.
        Respondé en 2 a 5 frases o una lista corta de pasos. No inventes funciones que no esten
        en este catalogo. Si te preguntan algo ajeno al sistema o que no sabes, decilo con
        amabilidad y sugerí consultar a la administración académica. Si preguntan "donde esta X",
        indicá el item del menú lateral.

        MODULOS:
        - Inscripción CUP: el postulante (estudiante o docente) se pre-inscribe, completa datos,
          sube documentos (título de bachiller, etc.), paga el arancel (pasarela Stripe) y sigue
          el estado de su postulación. Elige carrera de 1ª y 2ª opción.
        - Admisiones (admin): ver las postulaciones por gestión, validar documentos, agendar
          entrevista, aprobar o rechazar.
        - Pagos (admin): ver y editar el estado del pago (pendiente, pagado, observado, anulado).
        - Gestión CUP (admin): crear/configurar la gestión — cupos por carrera, cupo total, nota
          mínima de aprobación (60), cantidad de exámenes (3), máximo de estudiantes por grupo (70),
          carreras habilitadas; abrir o cerrar la inscripción.
        - Catálogos: Carreras, Materias, Aulas, Grupos, Horarios.
        - Asignaciones (admin): organizar los grupos. Arriba hay dos botones: "Asignar docentes"
          (elegir un docente, luego un grupo y marcar las materias que dicta ahí; un docente puede
          dictar en hasta 4 grupos) y "Asignar estudiantes" (entrar a un grupo y agregar estudiantes,
          de a uno o en masa; respeta el cupo del grupo). La lista de grupos es una tabla.
        - Notas (docente): planilla de calificaciones por materia y grupo; carga las notas de los
          exámenes (escala 0 a 100).
        - Mi boletín (estudiante): ve sus notas por materia, su promedio y el "Resultado del CUP".
        - Reportes / Dashboard (admin): KPIs, gráficos, comparativo entre gestiones, lista de
          postulantes filtrable y exportable (CSV/Excel) y un asistente de IA que filtra los reportes.
        - Usuarios y Roles (admin): cuentas, roles y permisos por módulo. Bitácora: auditoría.

        REGLAS CLAVE: un estudiante APRUEBA el CUP solo si aprueba las 4 materias, cada una con nota
        >= 60; si reprueba una queda REPROBADO; si le faltan notas queda Incompleto. Cada grupo admite
        máximo 70 estudiantes y un docente puede dictar en hasta 4 grupos. Estos límites se configuran
        en Gestión CUP. Cada usuario ve solo los módulos que su rol le permite.
        TXT;

    public function __construct(private HttpClientInterface $http)
    {
    }

    /** @return array{ok: bool, respuesta: string} */
    public function execute(string $consulta, string $rol = ''): array
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return ['ok' => false, 'respuesta' => 'Escribí tu pregunta. Por ejemplo: "¿cómo asigno docentes a un grupo?".'];
        }

        $apiKey = (string) ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '');
        if ($apiKey === '') {
            return ['ok' => false, 'respuesta' => 'El asistente de ayuda todavía no está configurado (falta la clave OPENAI_API_KEY).'];
        }
        $modelo = (string) ($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: self::MODELO_DEFECTO);

        $user = ($rol !== '' ? "Rol del usuario que pregunta: {$rol}.\n\n" : '') . "Pregunta:\n" . $consulta;

        try {
            $response = $this->http->request('POST', self::ENDPOINT, [
                'headers' => [
                    'authorization' => 'Bearer ' . $apiKey,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $modelo,
                    'temperature' => 0.2,
                    'max_tokens' => 500,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $user],
                    ],
                ],
                'timeout' => 25,
            ]);
            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);
        } catch (\Throwable) {
            return ['ok' => false, 'respuesta' => 'No pude responder en este momento. Probá de nuevo.'];
        }

        $texto = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($texto === '') {
            return ['ok' => false, 'respuesta' => 'No entendí bien la pregunta. ¿La podés reformular?'];
        }

        return ['ok' => true, 'respuesta' => $texto];
    }
}
