<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * - Pertes et casse déclarées à la clôture d'une tournée DR.
 * - Rôles de référence : le rôle CAISSIER (ajouté à la main en développement) et, sur une installation
 *   neuve, l'ensemble des rôles dont dépendent les droits d'accès (config/packages/security.yaml).
 */
final class Version20261111000000 extends AbstractMigration
{
    private const ROLES = [
        'SUPER_ADMIN' => 'Administrateur général',
        'ADMIN_PAYS' => 'Administrateur pays',
        'RESPONSABLE_USINE' => 'Responsable usine',
        'GESTIONNAIRE_DEPOT' => 'Gestionnaire dépôt',
        'DELEGUE' => 'Délégué commercial',
        'AGENT_COMMERCIAL' => 'Agent commercial',
        'GROSSISTE_USER' => 'Utilisateur grossiste',
        'CAISSIER' => 'Caissier',
    ];

    public function getDescription(): string
    {
        return 'Pertes de tournée DR, rôles de référence (dont CAISSIER)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('demande_consignation_ligne')->hasColumn('quantite_perdue')) {
            $this->addSql("ALTER TABLE demande_consignation_ligne ADD quantite_perdue NUMERIC(15, 3) DEFAULT '0.000' NOT NULL");
        }

        foreach (self::ROLES as $code => $libelle) {
            $this->addSql(
                'INSERT INTO role (code, libelle) SELECT :code, :libelle FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM role WHERE code = :code)',
                ['code' => $code, 'libelle' => $libelle]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Les rôles sont conservés : des comptes peuvent y être rattachés
        $this->addSql('ALTER TABLE demande_consignation_ligne DROP quantite_perdue');
    }
}
