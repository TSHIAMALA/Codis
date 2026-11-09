<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Valeurs manquantes dans les ENUM MySQL. Hors mode strict, MySQL enregistrait silencieusement
 * une chaîne vide (ex. annulation d'un BL : statut '' au lieu de 'ANNULEE').
 */
final class Version20261109140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ENUM : emplacement de type DR, livraison ANNULEE';
    }

    public function up(Schema $schema): void
    {
        $type = $this->connection->fetchOne("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'livraison' AND COLUMN_NAME = 'statut'");
        if ($type !== 'enum') {
            $this->write('Statuts déjà en VARCHAR (installation neuve), rien à faire.');
            return;
        }

        $this->addSql("ALTER TABLE emplacement_stock MODIFY type_emplacement ENUM('USINE','DEPOT','DR') NOT NULL DEFAULT 'DEPOT'");
        $this->addSql("UPDATE emplacement_stock SET type_emplacement = 'DR' WHERE agent_id IS NOT NULL AND type_emplacement = ''");
        $this->addSql("ALTER TABLE livraison MODIFY statut ENUM('PLANIFIEE','PREPARATION','EXPEDIEE','EN_TRANSIT','LIVREE','RECEPTION_CONFIRMEE','INCIDENT','ANNULEE') NOT NULL DEFAULT 'PLANIFIEE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE livraison MODIFY statut ENUM('PLANIFIEE','PREPARATION','EXPEDIEE','EN_TRANSIT','LIVREE','RECEPTION_CONFIRMEE','INCIDENT') NOT NULL DEFAULT 'PLANIFIEE'");
        $this->addSql("ALTER TABLE emplacement_stock MODIFY type_emplacement ENUM('USINE','DEPOT') NOT NULL DEFAULT 'DEPOT'");
    }
}
