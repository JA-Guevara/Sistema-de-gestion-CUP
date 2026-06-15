<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Observacion en el pago: el admin puede dejar una nota al editar el estado
 * (OBSERVADO/ANULADO). Los estados nuevos OBSERVADO/ANULADO son valores de
 * catalogo (la columna estado ya existe), no requieren cambio de esquema.
 */
final class Version20260615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pagos: columna observacion para la edicion de estado por el admin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pagos ADD observacion TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pagos DROP observacion');
    }
}
