<?php

declare(strict_types=1);

namespace App\Inscripcion\Domain\Catalog;

/**
 * Catalogo de requisitos documentales que el postulante debe presentar el dia
 * de la cita. Es la base del acta de control de recepcion: por cada requisito,
 * la administracion marca si lo entrego, si esta observado o si falta.
 *
 * Los requisitos OBLIGATORIOS forman el gate de aprobacion: solo se puede
 * aprobar (validar) la postulacion cuando TODOS los obligatorios estan
 * Entregados. Los opcionales suman pero no bloquean.
 *
 * Esta lista es facil de editar; si cambia la normativa del CUP, se ajusta aqui.
 */
final class RequisitoCatalog
{
    /**
     * @var array<string, list<array{codigo:string, etiqueta:string, obligatorio:bool}>>
     */
    private const REQUISITOS = [
        TipoPostulacion::ESTUDIANTE => [
            ['codigo' => 'ci', 'etiqueta' => 'Cedula de identidad', 'obligatorio' => true],
            ['codigo' => 'bachiller', 'etiqueta' => 'Certificado / Titulo de bachiller (o libreta de 6to)', 'obligatorio' => true],
            ['codigo' => 'nacimiento', 'etiqueta' => 'Certificado de nacimiento', 'obligatorio' => true],
            ['codigo' => 'fotos', 'etiqueta' => 'Fotografias 4x4', 'obligatorio' => true],
            ['codigo' => 'formulario', 'etiqueta' => 'Formulario de inscripcion', 'obligatorio' => true],
        ],
        TipoPostulacion::DOCENTE => [
            ['codigo' => 'ci', 'etiqueta' => 'Cedula de identidad', 'obligatorio' => true],
            ['codigo' => 'titulo', 'etiqueta' => 'Titulo profesional', 'obligatorio' => true],
            ['codigo' => 'cv', 'etiqueta' => 'Hoja de vida (CV)', 'obligatorio' => true],
            ['codigo' => 'maestria', 'etiqueta' => 'Diploma de maestria', 'obligatorio' => false],
            ['codigo' => 'diplomado', 'etiqueta' => 'Diplomado en educacion superior', 'obligatorio' => false],
            ['codigo' => 'experiencia', 'etiqueta' => 'Certificados de experiencia', 'obligatorio' => false],
        ],
    ];

    /**
     * Requisitos aplicables a un tipo de postulacion (estudiante/docente).
     *
     * @return list<array{codigo:string, etiqueta:string, obligatorio:bool}>
     */
    public static function paraTipo(string $tipo): array
    {
        return self::REQUISITOS[$tipo] ?? self::REQUISITOS[TipoPostulacion::ESTUDIANTE];
    }

    /** Codigos validos para un tipo (para validar el acta enviada). */
    public static function codigosValidos(string $tipo): array
    {
        return array_map(static fn (array $r): string => $r['codigo'], self::paraTipo($tipo));
    }

    public static function etiqueta(string $tipo, string $codigo): string
    {
        foreach (self::paraTipo($tipo) as $requisito) {
            if ($requisito['codigo'] === $codigo) {
                return $requisito['etiqueta'];
            }
        }

        return $codigo;
    }
}
