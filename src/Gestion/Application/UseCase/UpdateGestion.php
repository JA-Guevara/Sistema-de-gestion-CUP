<?php

declare(strict_types=1);

namespace App\Gestion\Application\UseCase;

use App\Academico\Carrera\Domain\Entity\Carrera;
use App\Academico\Carrera\Infrastructure\Persistence\CarreraRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Gestion\Application\DTO\CarreraGestionInput;
use App\Gestion\Application\DTO\GestionInput;
use App\Gestion\Application\DTO\PeriodoGestionInput;
use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Domain\Catalog\TipoAccionGestion;
use App\Gestion\Domain\Entity\CarreraGestion;
use App\Gestion\Domain\Entity\ConfiguracionGestion;
use App\Gestion\Domain\Entity\CupoGestion;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Domain\Entity\HistorialGestion;
use App\Gestion\Domain\Entity\PeriodoGestion;
use App\Gestion\Domain\Exception\GestionException;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class UpdateGestion
{
    public function __construct(
        private GestionRepository $gestiones,
        private CarreraRepository $catalogoCarreras,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(int $gestionId, GestionInput $input): Gestion
    {
        $gestion = $this->loadGestion($gestionId);
        $sameCodeGestion = $this->loadGestionByCode($input);
        $this->validateGestionData($input);
        $this->validateConfiguration($input);
        $this->validatePeriods($input);
        $this->validateCareers($input);
        $this->validateBusinessRules($gestion, $sameCodeGestion);
        $catalogCareers = $this->loadCatalogCareers($input);
        $this->updateGestion($gestion, $input, $catalogCareers);
        $this->saveGestion($gestion);
        $this->registerAudit($gestion, $input);
        $this->notifyGestionUpdated($gestion);

        return $gestion;
    }

    private function loadGestion(int $gestionId): Gestion
    {
        $gestion = $this->gestiones->findById($gestionId);
        if ($gestion === null) {
            throw new GestionException('La gestion solicitada no existe.');
        }

        return $gestion;
    }

    private function loadGestionByCode(GestionInput $input): ?Gestion
    {
        return $this->gestiones->findByCodigo($input->codigo);
    }

    private function validateGestionData(GestionInput $input): void
    {
        if (trim($input->codigo) === '' || trim($input->nombre) === '') {
            throw new GestionException('Codigo y nombre de gestion son obligatorios.');
        }
    }

    private function validateConfiguration(GestionInput $input): void
    {
        if ($input->cupoTotal <= 0 || $input->maxEstudiantesPorGrupo <= 0) {
            throw new GestionException('Los cupos y maximos deben ser mayores a cero.');
        }

        if ($input->maxGruposPorDocente < 1 || $input->maxGruposPorDocente > 4) {
            throw new GestionException('Un docente solo puede tener entre 1 y 4 grupos.');
        }

        if ($input->notaMinimaAprobacion < 0 || $input->notaMinimaAprobacion > 100) {
            throw new GestionException('La nota minima debe estar entre 0 y 100.');
        }

        if ($input->ponderacionesExamenes !== []) {
            if (count($input->ponderacionesExamenes) !== $input->cantidadExamenes) {
                throw new GestionException('Debes indicar la ponderacion de los 3 examenes (o dejarlas todas vacias).');
            }
            if (abs(array_sum($input->ponderacionesExamenes) - 100.0) > 0.5) {
                throw new GestionException('Las ponderaciones de los examenes deben sumar 100%.');
            }
        }
    }

    private function validatePeriods(GestionInput $input): void
    {
        foreach ($input->periodos as $periodo) {
            if (!$periodo instanceof PeriodoGestionInput || trim($periodo->tipoPeriodo) === '') {
                throw new GestionException('El cronograma contiene actividades invalidas.');
            }

            if ($periodo->fechaInicio === null || $periodo->fechaFin === null || $periodo->fechaFin < $periodo->fechaInicio) {
                throw new GestionException('Revise las fechas del cronograma academico.');
            }
        }
    }

    private function validateCareers(GestionInput $input): void
    {
        if ($input->carreras === []) {
            throw new GestionException('Debe seleccionar al menos una carrera habilitada.');
        }

        $selectedCareers = [];
        foreach ($input->carreras as $carrera) {
            if (!$carrera instanceof CarreraGestionInput) {
                throw new GestionException('La lista de carreras contiene un dato invalido.');
            }

            if (isset($selectedCareers[$carrera->carreraId])) {
                throw new GestionException('No puede repetir la misma carrera en la gestion.');
            }

            $selectedCareers[$carrera->carreraId] = true;

            if ($carrera->habilitada && $carrera->cupoCarrera <= 0) {
                throw new GestionException('Toda carrera habilitada debe tener cupo mayor a cero.');
            }
        }
    }

    /** @return array<int, Carrera> */
    private function loadCatalogCareers(GestionInput $input): array
    {
        $careers = [];
        foreach ($input->carreras as $carreraInput) {
            $career = $this->catalogoCarreras->findById($carreraInput->carreraId);
            if ($career === null || !$career->isActive()) {
                throw new GestionException('Una de las carreras seleccionadas no existe o esta inactiva.');
            }

            $careers[$career->id] = $career;
        }

        return $careers;
    }

    private function validateBusinessRules(Gestion $gestion, ?Gestion $sameCodeGestion): void
    {
        if ($gestion->estado === EstadoGestion::FINALIZADA) {
            throw new GestionException('No se puede modificar una gestion finalizada.');
        }

        if ($sameCodeGestion !== null && $sameCodeGestion->id !== $gestion->id) {
            throw new GestionException('Ya existe otra gestion con ese codigo.');
        }
    }

    private function updateGestion(Gestion $gestion, GestionInput $input, array $catalogCareers): void
    {
        $gestion->rename($input->codigo, $input->nombre, $input->descripcion);
        $gestion->configure($this->createOrUpdateConfiguration($gestion, $input));
        $gestion->replacePeriods($this->createPeriods($input));
        $gestion->replaceCareers($this->createCareers($input, $catalogCareers));
        $gestion->replaceGlobalQuota(CupoGestion::create($input->cupoTotal));
        $gestion->addHistory($this->createHistory($input));
    }

    private function createOrUpdateConfiguration(Gestion $gestion, GestionInput $input): ConfiguracionGestion
    {
        $configuracion = $gestion->configuracion ?? ConfiguracionGestion::create(
            $input->cupoTotal,
            $input->maxEstudiantesPorGrupo,
            $input->maxGruposPorDocente,
            $input->notaMinimaAprobacion,
            $input->cantidadExamenes,
            $input->permiteReinscripcion,
            $input->permiteCambioGrupo,
            $input->generarBitacora,
            $input->ponderacionesExamenes,
        );

        $configuracion->updateValues(
            $input->cupoTotal,
            $input->maxEstudiantesPorGrupo,
            $input->maxGruposPorDocente,
            $input->notaMinimaAprobacion,
            $input->cantidadExamenes,
            $input->permiteReinscripcion,
            $input->permiteCambioGrupo,
            $input->generarBitacora,
            $input->ponderacionesExamenes,
        );

        return $configuracion;
    }

    private function createPeriods(GestionInput $input): array
    {
        return array_map(
            static fn (PeriodoGestionInput $periodo): PeriodoGestion => PeriodoGestion::create($periodo->tipoPeriodo, $periodo->fechaInicio, $periodo->fechaFin),
            $input->periodos,
        );
    }

    private function createCareers(GestionInput $input, array $catalogCareers): array
    {
        return array_map(
            static fn (CarreraGestionInput $carrera): CarreraGestion => CarreraGestion::create($catalogCareers[$carrera->carreraId], $carrera->habilitada, $carrera->cupoCarrera),
            $input->carreras,
        );
    }

    private function createHistory(GestionInput $input): HistorialGestion
    {
        return HistorialGestion::create(TipoAccionGestion::ACTUALIZADA, 'Gestion CUP actualizada.', $input->actorUserId);
    }

    private function saveGestion(Gestion $gestion): void
    {
        $this->gestiones->save($gestion);
    }

    private function registerAudit(Gestion $gestion, GestionInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::GESTIONES_CUP, sprintf('Se actualizo la gestion %s.', $gestion->codigo), $input->actorUserId);
    }

    private function notifyGestionUpdated(Gestion $gestion): void
    {
        // Punto de extension para notificaciones administrativas.
    }
}
