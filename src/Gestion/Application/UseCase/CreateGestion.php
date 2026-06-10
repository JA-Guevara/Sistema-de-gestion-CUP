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
use App\Gestion\Domain\Catalog\TipoAccionGestion;
use App\Gestion\Domain\Entity\CarreraGestion;
use App\Gestion\Domain\Entity\ConfiguracionGestion;
use App\Gestion\Domain\Entity\CupoGestion;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Domain\Entity\HistorialGestion;
use App\Gestion\Domain\Entity\PeriodoGestion;
use App\Gestion\Domain\Exception\GestionException;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class CreateGestion
{
    public function __construct(
        private GestionRepository $gestiones,
        private CarreraRepository $catalogoCarreras,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(GestionInput $input): Gestion
    {
        $existingGestion = $this->loadExistingGestion($input);
        $this->validateGestionData($input);
        $this->validateConfiguration($input);
        $this->validatePeriods($input);
        $this->validateCareers($input);
        $this->validateBusinessRules($existingGestion);
        $catalogCareers = $this->loadCatalogCareers($input);
        $gestion = $this->createGestion($input, $catalogCareers);
        $this->saveGestion($gestion);
        $this->registerAudit($gestion, $input);
        $this->notifyGestionCreated($gestion);

        return $gestion;
    }

    private function loadExistingGestion(GestionInput $input): ?Gestion
    {
        return $this->gestiones->findByCodigo($input->codigo);
    }

    private function validateGestionData(GestionInput $input): void
    {
        if (trim($input->codigo) === '') {
            throw new GestionException('El codigo de la gestion es obligatorio.');
        }

        if (trim($input->nombre) === '') {
            throw new GestionException('El nombre de la gestion es obligatorio.');
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

        if ($input->cantidadExamenes !== 3) {
            throw new GestionException('El CUP debe configurar exactamente 3 examenes por materia.');
        }
    }

    private function validatePeriods(GestionInput $input): void
    {
        foreach ($input->periodos as $periodo) {
            if (!$periodo instanceof PeriodoGestionInput) {
                throw new GestionException('El cronograma contiene un periodo invalido.');
            }

            $this->validatePeriod($periodo);
        }
    }

    private function validatePeriod(PeriodoGestionInput $periodo): void
    {
        if (trim($periodo->tipoPeriodo) === '') {
            throw new GestionException('Toda actividad del cronograma debe tener nombre.');
        }

        if ($periodo->fechaInicio === null || $periodo->fechaFin === null) {
            throw new GestionException('Todo periodo debe tener fecha de inicio y fin.');
        }

        if ($periodo->fechaFin < $periodo->fechaInicio) {
            throw new GestionException('La fecha fin no puede ser menor a la fecha inicio.');
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

    private function validateBusinessRules(?Gestion $existingGestion): void
    {
        if ($existingGestion !== null) {
            throw new GestionException('Ya existe una gestion con ese codigo.');
        }
    }

    private function createGestion(GestionInput $input, array $catalogCareers): Gestion
    {
        $gestion = new Gestion();
        $gestion->rename($input->codigo, $input->nombre, $input->descripcion);
        $gestion->creadoPor = $input->actorUserId;
        $gestion->configure($this->createConfiguration($input));
        $gestion->replacePeriods($this->createPeriods($input));
        $gestion->replaceCareers($this->createCareers($input, $catalogCareers));
        $gestion->cupos->add($this->createGlobalQuota($gestion, $input));
        $gestion->addHistory($this->createHistory($input));

        return $gestion;
    }

    private function createConfiguration(GestionInput $input): ConfiguracionGestion
    {
        return ConfiguracionGestion::create(
            $input->cupoTotal,
            $input->maxEstudiantesPorGrupo,
            $input->maxGruposPorDocente,
            $input->notaMinimaAprobacion,
            $input->cantidadExamenes,
            $input->permiteReinscripcion,
            $input->permiteCambioGrupo,
            $input->generarBitacora,
        );
    }

    /** @return list<PeriodoGestion> */
    private function createPeriods(GestionInput $input): array
    {
        return array_map(
            static fn (PeriodoGestionInput $periodo): PeriodoGestion => PeriodoGestion::create(
                $periodo->tipoPeriodo,
                $periodo->fechaInicio,
                $periodo->fechaFin,
            ),
            $input->periodos,
        );
    }

    /** @return list<CarreraGestion> */
    private function createCareers(GestionInput $input, array $catalogCareers): array
    {
        return array_map(
            static fn (CarreraGestionInput $carrera): CarreraGestion => CarreraGestion::create(
                $catalogCareers[$carrera->carreraId],
                $carrera->habilitada,
                $carrera->cupoCarrera,
            ),
            $input->carreras,
        );
    }

    private function createGlobalQuota(Gestion $gestion, GestionInput $input): CupoGestion
    {
        $cupo = CupoGestion::create($input->cupoTotal);
        $cupo->gestion = $gestion;

        return $cupo;
    }

    private function createHistory(GestionInput $input): HistorialGestion
    {
        return HistorialGestion::create(
            TipoAccionGestion::CREADA,
            'Gestion CUP creada.',
            $input->actorUserId,
        );
    }

    private function saveGestion(Gestion $gestion): void
    {
        $this->gestiones->save($gestion);
    }

    private function registerAudit(Gestion $gestion, GestionInput $input): void
    {
        $this->audit->execute(
            ActionCatalog::CREATE,
            ModuleCatalog::GESTIONES_CUP,
            sprintf('Se creo la gestion %s.', $gestion->codigo),
            $input->actorUserId,
        );
    }

    private function notifyGestionCreated(Gestion $gestion): void
    {
        // Punto de extension para notificaciones administrativas.
    }
}
