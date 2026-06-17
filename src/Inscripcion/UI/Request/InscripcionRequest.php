<?php

declare(strict_types=1);

namespace App\Inscripcion\UI\Request;

use App\Academico\Materia\Domain\Catalog\AreaCatalog;
use App\Inscripcion\Application\DTO\InscripcionInput;
use App\Inscripcion\Domain\Catalog\ModalidadPostulacion;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use Symfony\Component\HttpFoundation\Request;

final class InscripcionRequest
{
    public static function fromRequest(Request $request): InscripcionInput
    {
        $fechaNacimiento = null;
        $fechaStr = $request->request->get('fechaNacimiento');
        if (is_string($fechaStr) && trim($fechaStr) !== '') {
            $fechaNacimiento = new \DateTimeImmutable(trim($fechaStr));
        }

        $tipo = strtoupper(trim((string) $request->request->get('tipo', TipoPostulacion::ESTUDIANTE)));
        $modalidad = strtoupper(trim((string) $request->request->get('modalidad', ModalidadPostulacion::PRESENCIAL)));
        $accion = strtoupper(trim((string) $request->request->get('accion', InscripcionInput::ACCION_GUARDAR)));

        return new InscripcionInput(
            tipo: TipoPostulacion::isValid($tipo) ? $tipo : TipoPostulacion::ESTUDIANTE,
            modalidad: ModalidadPostulacion::isValid($modalidad) ? $modalidad : ModalidadPostulacion::PRESENCIAL,
            ci: trim((string) $request->request->get('ci', '')),
            nombres: trim((string) $request->request->get('nombres', '')),
            apellidos: trim((string) $request->request->get('apellidos', '')),
            fechaNacimiento: $fechaNacimiento ?? new \DateTimeImmutable(),
            sexo: trim((string) $request->request->get('sexo', '')),
            direccion: self::nullableString($request->request->get('direccion')),
            telefono: self::nullableString($request->request->get('telefono')),
            email: trim((string) $request->request->get('email', '')),
            colegioProcedencia: self::nullableString($request->request->get('colegioProcedencia')),
            ciudad: self::nullableString($request->request->get('ciudad')),
            tituloBachiller: $request->request->has('tituloBachiller'),
            turnoPreferencia: self::nullableString($request->request->get('turnoPreferencia')),
            carreraId: (int) $request->request->get('carrera', 0),
            carreraSegundaId: (int) $request->request->get('carreraSegunda', 0),
            docenteProfesion: self::nullableString($request->request->get('docenteProfesion')),
            docenteMaestria: $request->request->has('docenteMaestria'),
            docenteDiplomado: $request->request->has('docenteDiplomado'),
            docenteExperiencia: self::nullableString($request->request->get('docenteExperiencia')),
            docenteAreas: AreaCatalog::filterValid((array) $request->request->all('docenteAreas')),
            otros: self::nullableString($request->request->get('otros')),
            accion: $accion === InscripcionInput::ACCION_PRESENTAR ? InscripcionInput::ACCION_PRESENTAR : InscripcionInput::ACCION_GUARDAR,
            actorUserId: self::actorUserId($request),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
