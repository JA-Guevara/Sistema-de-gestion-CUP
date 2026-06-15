<?php

declare(strict_types=1);

namespace App\Notas\Application\UseCase;

use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\EventLog\NotasEvents;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Notas\Application\Security\PlanillaAccessPolicy;
use App\Notas\Domain\Entity\Nota;
use App\Notas\Domain\Exception\NotaException;
use App\Notas\Infrastructure\Persistence\AsignacionGrupoRepository;
use App\Notas\Infrastructure\Persistence\NotaRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Carga masiva de notas desde un CSV. Formato esperado (igual al exportado):
 * CI, Apellidos, Nombres, Examen 1, Examen 2, ... (las columnas extra se ignoran).
 * Solo se aceptan notas de estudiantes asignados a ese grupo/materia.
 */
final readonly class ImportarNotas
{
    private const DEFAULT_EXAMENES = 2;

    public function __construct(
        private MateriaRepository $materias,
        private GrupoRepository $grupos,
        private GestionRepository $gestiones,
        private AsignacionGrupoRepository $asignacionesGrupo,
        private NotaRepository $notas,
        private UserRepository $users,
        private PlanillaAccessPolicy $accessPolicy,
        private NotasEvents $events,
    ) {
    }

    public function execute(int $materiaId, int $grupoId, UploadedFile $archivo, ?int $actorUserId): int
    {
        $gestion = $this->gestiones->findActive();
        if ($gestion === null) {
            throw new NotaException('No hay una gestion activa en este momento.');
        }

        $materia = $this->materias->findById($materiaId);
        $grupo = $this->grupos->findById($grupoId);
        if ($materia === null || $grupo === null) {
            throw new NotaException('Materia o grupo no encontrados.');
        }

        // Autorizacion a nivel de objeto: solo el docente asignado (o admin) importa.
        $this->accessPolicy->assertPuede($actorUserId, $materiaId, $grupoId, (int) $gestion->id);

        $cantidadExamenes = max(1, $gestion->configuracion?->cantidadExamenes ?? self::DEFAULT_EXAMENES);
        $actor = $actorUserId !== null ? $this->users->findById($actorUserId) : null;

        $inscritosPorCi = [];
        foreach ($this->asignacionesGrupo->listByMateriaAndGrupo($materia->id, $grupo->id) as $asignacion) {
            $inscritosPorCi[trim($asignacion->inscripcion->ci)] = $asignacion->inscripcion;
        }

        $guardadas = 0;
        foreach ($this->leerFilas($archivo) as $cols) {
            $ci = isset($cols[0]) ? trim((string) $cols[0]) : '';
            if ($ci === '' || strtolower($ci) === 'ci') {
                continue; // fila vacia o encabezado
            }
            if (!isset($inscritosPorCi[$ci])) {
                continue; // no pertenece a este grupo/materia
            }

            $inscripcion = $inscritosPorCi[$ci];
            for ($examen = 1; $examen <= $cantidadExamenes; $examen++) {
                $valor = $cols[2 + $examen] ?? null; // CI, Apellidos, Nombres, Examen1...
                if ($valor === null || trim((string) $valor) === '' || !is_numeric($valor)) {
                    continue;
                }

                $entero = (int) $valor;
                if ($entero < 0 || $entero > 100) {
                    throw new NotaException(sprintf('La nota de CI %s debe estar entre 0 y 100 (recibido %s).', $ci, $valor));
                }

                $nota = $this->notas->findOne($inscripcion->id, $materia->id, $examen);
                if ($nota === null) {
                    $nota = new Nota();
                    $nota->inscripcion = $inscripcion;
                    $nota->materia = $materia;
                    $nota->numeroExamen = $examen;
                    $this->notas->persist($nota);
                }

                $nota->setValor($entero, null, $actor);
                $guardadas++;
            }
        }

        if ($guardadas > 0) {
            $this->notas->flush();
            $this->events->notasRegistradas(
                sprintf('%s - %s', $materia->codigo, $materia->nombre),
                $grupo->codigo,
                $guardadas,
                $actorUserId,
            );
        }

        return $guardadas;
    }

    /**
     * @return list<list<string>>
     */
    private function leerFilas(UploadedFile $archivo): array
    {
        $contenido = file_get_contents($archivo->getPathname());
        if ($contenido === false || trim($contenido) === '') {
            throw new NotaException('El archivo esta vacio o no se pudo leer.');
        }

        // Detecta el separador (coma o punto y coma) por la primera linea.
        $primeraLinea = strtok($contenido, "\n");
        $delimitador = (substr_count((string) $primeraLinea, ';') > substr_count((string) $primeraLinea, ',')) ? ';' : ',';

        $filas = [];
        foreach (preg_split('/\r\n|\r|\n/', $contenido) ?: [] as $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $filas[] = str_getcsv($linea, $delimitador);
        }

        return $filas;
    }
}
