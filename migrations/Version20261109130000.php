<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2 — fiabilité des stocks :
 *  - passage en InnoDB des tables restées en MyISAM (transactions et verrous impossibles sinon) ;
 *  - emplacement de stock rattaché à un DR (stock confié en tournée) ;
 *  - conditionnement sur les lignes de consignation (cartons, paquets...).
 *
 * Les id existants sont en BIGINT UNSIGNED : les nouvelles clés étrangères suivent ce type.
 */
final class Version20261109130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'InnoDB pour les consignations, emplacement de stock DR, conditionnement des lignes de consignation';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('emplacement_stock')->hasColumn('agent_id')) {
            $this->write('Colonnes déjà présentes (installation neuve), rien à faire.');
            return;
        }

        $this->addSql('ALTER TABLE demande_consignation ENGINE=InnoDB');
        $this->addSql('ALTER TABLE demande_consignation_ligne ENGINE=InnoDB');
        $this->addSql('ALTER TABLE messenger_messages ENGINE=InnoDB');

        $this->addSql('ALTER TABLE emplacement_stock ADD agent_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_EC96D0D53414710B ON emplacement_stock (agent_id)');
        $this->addSql('ALTER TABLE emplacement_stock ADD CONSTRAINT FK_EC96D0D53414710B FOREIGN KEY (agent_id) REFERENCES agent_commercial (id)');

        $this->addSql('ALTER TABLE demande_consignation_ligne ADD conditionnement_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_202EDBCA222637 ON demande_consignation_ligne (conditionnement_id)');
        $this->addSql('ALTER TABLE demande_consignation_ligne ADD CONSTRAINT FK_202EDBCA222637 FOREIGN KEY (conditionnement_id) REFERENCES conditionnement (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_consignation_ligne DROP FOREIGN KEY FK_202EDBCA222637');
        $this->addSql('DROP INDEX IDX_202EDBCA222637 ON demande_consignation_ligne');
        $this->addSql('ALTER TABLE demande_consignation_ligne DROP conditionnement_id');

        $this->addSql('ALTER TABLE emplacement_stock DROP FOREIGN KEY FK_EC96D0D53414710B');
        $this->addSql('DROP INDEX IDX_EC96D0D53414710B ON emplacement_stock');
        $this->addSql('ALTER TABLE emplacement_stock DROP agent_id');
        // Le moteur InnoDB est conservé (revenir en MyISAM n'apporte rien)
    }
}
