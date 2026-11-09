<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligne une base existante sur le mapping Doctrine :
 *  - ENUM convertis en VARCHAR (les valeurs autorisées sont garanties par config/packages/workflow.yaml) ;
 *  - colonnes de jointure en BIGINT UNSIGNED comme les identifiants, ce qui permet d'ajouter
 *    les clés étrangères manquantes (consignations, grossiste.dr_id, agent_commercial.superviseur_id) ;
 *  - collation commune (utf8mb4_unicode_ci) pour toutes les tables ;
 *  - noms d'index et de clés étrangères normalisés ; index utiles et contraintes d'unicité conservés.
 * Aucune colonne ni donnée n'est supprimée. Les orphelins ont été vérifiés avant l'ajout des clés étrangères.
 */
final class Version20261110000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alignement du schéma existant sur le mapping Doctrine (types, clés étrangères, index)';
    }

    public function up(Schema $schema): void
    {
        $type = $this->connection->fetchOne("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commande' AND COLUMN_NAME = 'statut'");
        if ($type !== 'enum') {
            $this->write('Schéma déjà aligné (installation neuve), rien à faire.');
            return;
        }

        // Les colonnes engagées dans des clés étrangères ne peuvent changer de type qu'avec la vérification désactivée
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('ALTER TABLE demande_consignation CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE demande_consignation_ligne CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE messenger_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE agent_commercial CHANGE actif actif TINYINT NOT NULL, CHANGE type_agent type_agent VARCHAR(50) DEFAULT \'DR\' NOT NULL, CHANGE superviseur_id superviseur_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_commercial ADD CONSTRAINT FK_71522A1B7BB80FF FOREIGN KEY (superviseur_id) REFERENCES agent_commercial (id)');
        $this->addSql('CREATE INDEX IDX_71522A1B7BB80FF ON agent_commercial (superviseur_id)');
        $this->addSql('ALTER TABLE agent_commercial RENAME INDEX utilisateur_id TO UNIQ_71522A1FB88E14F');
        $this->addSql('ALTER TABLE agent_commercial RENAME INDEX fk_agent_pays TO IDX_71522A1A6E44244');
        $this->addSql('ALTER TABLE agent_commercial RENAME INDEX fk_agent_region TO IDX_71522A198260155');
        $this->addSql('ALTER TABLE agent_commercial RENAME INDEX fk_agent_zone TO IDX_71522A19F2C3FAB');
        $this->addSql('ALTER TABLE agent_commercial RENAME INDEX fk_agent_delegue TO IDX_71522A1C283956F');
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY `fk_audit_utilisateur`');
        $this->addSql('ALTER TABLE audit_log CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE audit_log RENAME INDEX fk_audit_utilisateur TO IDX_F6E1C0F5FB88E14F');
        $this->addSql('ALTER TABLE categorie_produit CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE categorie_produit RENAME INDEX code TO UNIQ_7626428577153098');
        $this->addSql('ALTER TABLE commande CHANGE type_commande type_commande VARCHAR(20) NOT NULL, CHANGE statut statut VARCHAR(30) DEFAULT \'BROUILLON\' NOT NULL, CHANGE created_by created_by BIGINT UNSIGNED DEFAULT NULL, CHANGE observation observation LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE commande RENAME INDEX numero TO UNIQ_6EEAA67DF55AE19E');
        $this->addSql('ALTER TABLE commande RENAME INDEX fk_commande_grossiste TO IDX_6EEAA67D407EB501');
        $this->addSql('ALTER TABLE commande RENAME INDEX fk_commande_detailant TO IDX_6EEAA67DDEFDB85C');
        $this->addSql('ALTER TABLE commande RENAME INDEX fk_commande_delegue TO IDX_6EEAA67DC283956F');
        $this->addSql('ALTER TABLE commande RENAME INDEX fk_commande_agent TO IDX_6EEAA67D3414710B');
        $this->addSql('ALTER TABLE commande RENAME INDEX fk_commande_depot TO IDX_6EEAA67D8510D4DE');
        $this->addSql('ALTER TABLE commande RENAME INDEX fk_commande_user TO IDX_6EEAA67DDE12AB56');
        $this->addSql('ALTER TABLE commande_ligne CHANGE conditionnement_id conditionnement_id BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE commande_ligne RENAME INDEX fk_commande_ligne_commande TO IDX_6E98044082EA2E54');
        $this->addSql('ALTER TABLE commande_ligne RENAME INDEX fk_commande_ligne_produit TO IDX_6E980440F347EFB');
        $this->addSql('ALTER TABLE commande_ligne RENAME INDEX fk_commande_ligne_conditionnement TO IDX_6E980440A222637');
        $this->addSql('ALTER TABLE conditionnement CHANGE quantite_conversion quantite_conversion NUMERIC(15, 3) NOT NULL');
        $this->addSql('ALTER TABLE conditionnement RENAME INDEX fk_conditionnement_produit TO IDX_3F4BEA3AF347EFB');
        $this->addSql('ALTER TABLE delegue CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE delegue RENAME INDEX utilisateur_id TO UNIQ_C89E71DDFB88E14F');
        $this->addSql('ALTER TABLE delegue RENAME INDEX fk_delegue_pays TO IDX_C89E71DDA6E44244');
        $this->addSql('ALTER TABLE delegue RENAME INDEX fk_delegue_region TO IDX_C89E71DD98260155');
        $this->addSql('ALTER TABLE delegue RENAME INDEX fk_delegue_zone TO IDX_C89E71DD9F2C3FAB');
        $this->addSql('ALTER TABLE delegue_grossiste CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE delegue_grossiste RENAME INDEX fk_dg_grossiste TO IDX_DD05F5AA407EB501');
        $this->addSql('ALTER TABLE demande_consignation CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE dr_id dr_id BIGINT UNSIGNED NOT NULL, CHANGE depot_id depot_id BIGINT UNSIGNED NOT NULL, CHANGE superviseur_id superviseur_id BIGINT UNSIGNED DEFAULT NULL, CHANGE asm_id asm_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_consignation ADD CONSTRAINT FK_4AD146BFF2A652A5 FOREIGN KEY (dr_id) REFERENCES agent_commercial (id)');
        $this->addSql('ALTER TABLE demande_consignation ADD CONSTRAINT FK_4AD146BF8510D4DE FOREIGN KEY (depot_id) REFERENCES depot (id)');
        $this->addSql('ALTER TABLE demande_consignation ADD CONSTRAINT FK_4AD146BFB7BB80FF FOREIGN KEY (superviseur_id) REFERENCES agent_commercial (id)');
        $this->addSql('ALTER TABLE demande_consignation ADD CONSTRAINT FK_4AD146BF9C54D4BF FOREIGN KEY (asm_id) REFERENCES agent_commercial (id)');
        $this->addSql('CREATE INDEX idx_consignation_statut ON demande_consignation (statut)');
        $this->addSql('ALTER TABLE demande_consignation_ligne CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE demande_id demande_id BIGINT UNSIGNED NOT NULL, CHANGE produit_id produit_id BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE demande_consignation_ligne ADD CONSTRAINT FK_202EDBC80E95E18 FOREIGN KEY (demande_id) REFERENCES demande_consignation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demande_consignation_ligne ADD CONSTRAINT FK_202EDBCF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE depot CHANGE type_depot type_depot VARCHAR(30) DEFAULT \'REGIONAL\' NOT NULL, CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE depot RENAME INDEX code TO UNIQ_47948BBC77153098');
        $this->addSql('ALTER TABLE depot RENAME INDEX fk_depot_pays TO IDX_47948BBCA6E44244');
        $this->addSql('ALTER TABLE depot RENAME INDEX fk_depot_region TO IDX_47948BBC98260155');
        $this->addSql('ALTER TABLE depot RENAME INDEX fk_depot_ville TO IDX_47948BBCA73F0036');
        $this->addSql('ALTER TABLE detailant CHANGE statut statut VARCHAR(20) DEFAULT \'ACTIF\' NOT NULL');
        $this->addSql('ALTER TABLE detailant RENAME INDEX code TO UNIQ_A4D34D8277153098');
        $this->addSql('ALTER TABLE detailant RENAME INDEX fk_detailant_pays TO IDX_A4D34D82A6E44244');
        $this->addSql('ALTER TABLE detailant RENAME INDEX fk_detailant_region TO IDX_A4D34D8298260155');
        $this->addSql('ALTER TABLE detailant RENAME INDEX fk_detailant_ville TO IDX_A4D34D82A73F0036');
        $this->addSql('ALTER TABLE detailant RENAME INDEX fk_detailant_zone TO IDX_A4D34D829F2C3FAB');
        $this->addSql('ALTER TABLE emplacement_stock CHANGE type_emplacement type_emplacement VARCHAR(20) NOT NULL, CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE emplacement_stock RENAME INDEX fk_emplacement_usine TO IDX_EC96D0D5C0130686');
        $this->addSql('ALTER TABLE emplacement_stock RENAME INDEX fk_emplacement_depot TO IDX_EC96D0D58510D4DE');
        $this->addSql('ALTER TABLE grossiste CHANGE statut statut VARCHAR(20) DEFAULT \'ACTIF\' NOT NULL, CHANGE type_distribution type_distribution VARCHAR(50) DEFAULT \'DISTRIBUTEUR_AGREE\' NOT NULL, CHANGE dr_id dr_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE grossiste ADD CONSTRAINT FK_1856F0F7F2A652A5 FOREIGN KEY (dr_id) REFERENCES agent_commercial (id)');
        $this->addSql('CREATE INDEX IDX_1856F0F7F2A652A5 ON grossiste (dr_id)');
        $this->addSql('ALTER TABLE grossiste RENAME INDEX code TO UNIQ_1856F0F777153098');
        $this->addSql('ALTER TABLE grossiste RENAME INDEX fk_grossiste_pays TO IDX_1856F0F7A6E44244');
        $this->addSql('ALTER TABLE grossiste RENAME INDEX fk_grossiste_region TO IDX_1856F0F798260155');
        $this->addSql('ALTER TABLE grossiste RENAME INDEX fk_grossiste_ville TO IDX_1856F0F7A73F0036');
        $this->addSql('ALTER TABLE grossiste RENAME INDEX fk_grossiste_zone TO IDX_1856F0F79F2C3FAB');
        $this->addSql('ALTER TABLE livraison CHANGE depot_id depot_id BIGINT UNSIGNED DEFAULT NULL, CHANGE statut statut VARCHAR(30) DEFAULT \'PLANIFIEE\' NOT NULL, CHANGE observation observation LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE livraison RENAME INDEX numero TO UNIQ_A60C9F1FF55AE19E');
        $this->addSql('ALTER TABLE livraison RENAME INDEX fk_livraison_commande TO IDX_A60C9F1F82EA2E54');
        $this->addSql('ALTER TABLE livraison RENAME INDEX fk_livraison_depot TO IDX_A60C9F1F8510D4DE');
        $this->addSql('ALTER TABLE livraison_ligne CHANGE quantite_livree quantite_livree NUMERIC(15, 3) DEFAULT NULL');
        $this->addSql('ALTER TABLE livraison_ligne RENAME INDEX fk_livraison_ligne_livraison TO IDX_EB3DA9FA8E54FB25');
        $this->addSql('ALTER TABLE livraison_ligne RENAME INDEX fk_livraison_ligne_produit TO IDX_EB3DA9FAF347EFB');
        $this->addSql('ALTER TABLE livraison_ligne RENAME INDEX fk_livraison_ligne_lot TO IDX_EB3DA9FAA8CBA5F7');
        $this->addSql('ALTER TABLE livraison_ligne RENAME INDEX fk_livraison_ligne_conditionnement TO IDX_EB3DA9FAA222637');
        $this->addSql('ALTER TABLE lot CHANGE usine_id usine_id BIGINT UNSIGNED DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'ACTIF\' NOT NULL');
        $this->addSql('ALTER TABLE lot RENAME INDEX fk_lot_produit TO IDX_B81291BF347EFB');
        $this->addSql('ALTER TABLE lot RENAME INDEX fk_lot_usine TO IDX_B81291BC0130686');
        $this->addSql('ALTER TABLE marque CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE marque RENAME INDEX code TO UNIQ_5A6F91CE77153098');
        $this->addSql('ALTER TABLE mouvement_stock CHANGE type_mouvement type_mouvement VARCHAR(30) NOT NULL, CHANGE utilisateur_id utilisateur_id BIGINT UNSIGNED DEFAULT NULL, CHANGE observation observation LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_mvt_reference ON mouvement_stock (reference_type, reference_id)');
        $this->addSql('ALTER TABLE mouvement_stock RENAME INDEX fk_mvt_produit TO IDX_61E2C8EBF347EFB');
        $this->addSql('ALTER TABLE mouvement_stock RENAME INDEX fk_mvt_lot TO IDX_61E2C8EBA8CBA5F7');
        $this->addSql('ALTER TABLE mouvement_stock RENAME INDEX fk_mvt_conditionnement TO IDX_61E2C8EBA222637');
        $this->addSql('ALTER TABLE mouvement_stock RENAME INDEX fk_mvt_source TO IDX_61E2C8EBBC9D4F59');
        $this->addSql('ALTER TABLE mouvement_stock RENAME INDEX fk_mvt_destination TO IDX_61E2C8EB55799AF7');
        $this->addSql('ALTER TABLE mouvement_stock RENAME INDEX fk_mvt_utilisateur TO IDX_61E2C8EBFB88E14F');
        $this->addSql('ALTER TABLE paiement CHANGE mode_paiement mode_paiement VARCHAR(30) DEFAULT \'ESPECES\' NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'VALIDE\' NOT NULL, CHANGE observation observation LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE paiement RENAME INDEX fk_paiement_commande TO IDX_B1DC7A1E82EA2E54');
        $this->addSql('ALTER TABLE paiement RENAME INDEX fk_paiement_utilisateur TO IDX_B1DC7A1EFB88E14F');
        $this->addSql('ALTER TABLE pays CHANGE actif actif TINYINT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE pays RENAME INDEX code TO UNIQ_349F3CAE77153098');
        $this->addSql('ALTER TABLE portefeuille_client CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE portefeuille_client RENAME INDEX fk_portefeuille_agent TO IDX_C33BA9913414710B');
        $this->addSql('ALTER TABLE portefeuille_client RENAME INDEX fk_portefeuille_detailant TO IDX_C33BA991DEFDB85C');
        $this->addSql('ALTER TABLE production CHANGE utilisateur_id utilisateur_id BIGINT UNSIGNED DEFAULT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'BROUILLON\' NOT NULL, CHANGE observation observation LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE production RENAME INDEX numero TO UNIQ_D3EDB1E0F55AE19E');
        $this->addSql('ALTER TABLE production RENAME INDEX fk_production_usine TO IDX_D3EDB1E0C0130686');
        $this->addSql('ALTER TABLE production RENAME INDEX fk_production_utilisateur TO IDX_D3EDB1E0FB88E14F');
        $this->addSql('ALTER TABLE production_ligne CHANGE lot_id lot_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE production_ligne RENAME INDEX fk_production_ligne_production TO IDX_4DF11FB5ECC6147F');
        $this->addSql('ALTER TABLE production_ligne RENAME INDEX fk_production_ligne_produit TO IDX_4DF11FB5F347EFB');
        $this->addSql('ALTER TABLE production_ligne RENAME INDEX fk_production_ligne_lot TO IDX_4DF11FB5A8CBA5F7');
        $this->addSql('ALTER TABLE production_ligne RENAME INDEX fk_production_ligne_conditionnement TO IDX_4DF11FB5A222637');
        $this->addSql('ALTER TABLE produit CHANGE description description LONGTEXT DEFAULT NULL, CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE produit RENAME INDEX code TO UNIQ_29A5EC2777153098');
        $this->addSql('ALTER TABLE produit RENAME INDEX idx_produit_marque TO IDX_29A5EC274827B9B2');
        $this->addSql('ALTER TABLE produit RENAME INDEX fk_produit_categorie TO IDX_29A5EC27BCF5E72D');
        $this->addSql('ALTER TABLE region DROP FOREIGN KEY `fk_region_pays`');
        $this->addSql('ALTER TABLE region CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE region ADD CONSTRAINT FK_F62F176A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id)');
        $this->addSql('ALTER TABLE region RENAME INDEX idx_region_pays TO IDX_F62F176A6E44244');
        $this->addSql('ALTER TABLE role RENAME INDEX code TO UNIQ_57698A6A77153098');
        $this->addSql('ALTER TABLE stock CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE stock RENAME INDEX idx_stock_emplacement TO IDX_4B365660C4598A51');
        $this->addSql('ALTER TABLE stock RENAME INDEX idx_stock_produit TO IDX_4B365660F347EFB');
        $this->addSql('ALTER TABLE stock RENAME INDEX fk_stock_lot TO IDX_4B365660A8CBA5F7');
        $this->addSql('ALTER TABLE stock RENAME INDEX fk_stock_conditionnement TO IDX_4B365660A222637');
        $this->addSql('ALTER TABLE tarif DROP FOREIGN KEY `fk_tarif_zone`');
        $this->addSql('ALTER TABLE tarif CHANGE conditionnement_id conditionnement_id BIGINT UNSIGNED NOT NULL, CHANGE type_client type_client VARCHAR(20) NOT NULL, CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE tarif ADD CONSTRAINT FK_E7189C99F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone_commerciale (id)');
        $this->addSql('ALTER TABLE tarif RENAME INDEX fk_tarif_pays TO IDX_E7189C9A6E44244');
        $this->addSql('ALTER TABLE tarif RENAME INDEX fk_tarif_region TO IDX_E7189C998260155');
        $this->addSql('ALTER TABLE tarif RENAME INDEX fk_tarif_zone TO IDX_E7189C99F2C3FAB');
        $this->addSql('ALTER TABLE tarif RENAME INDEX fk_tarif_produit TO IDX_E7189C9F347EFB');
        $this->addSql('ALTER TABLE tarif RENAME INDEX fk_tarif_conditionnement TO IDX_E7189C9A222637');
        $this->addSql('ALTER TABLE transfert_stock CHANGE statut statut VARCHAR(30) DEFAULT \'BROUILLON\' NOT NULL, CHANGE utilisateur_id utilisateur_id BIGINT UNSIGNED DEFAULT NULL, CHANGE observation observation LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE transfert_stock RENAME INDEX numero TO UNIQ_69A78AA9F55AE19E');
        $this->addSql('ALTER TABLE transfert_stock RENAME INDEX fk_transfert_source TO IDX_69A78AA9BC9D4F59');
        $this->addSql('ALTER TABLE transfert_stock RENAME INDEX fk_transfert_destination TO IDX_69A78AA955799AF7');
        $this->addSql('ALTER TABLE transfert_stock RENAME INDEX fk_transfert_utilisateur TO IDX_69A78AA9FB88E14F');
        $this->addSql('ALTER TABLE transfert_stock_ligne CHANGE quantite_recue quantite_recue NUMERIC(15, 3) DEFAULT NULL');
        $this->addSql('ALTER TABLE transfert_stock_ligne RENAME INDEX fk_tsl_transfert TO IDX_8F370E193C9C4BAD');
        $this->addSql('ALTER TABLE transfert_stock_ligne RENAME INDEX fk_tsl_produit TO IDX_8F370E19F347EFB');
        $this->addSql('ALTER TABLE transfert_stock_ligne RENAME INDEX fk_tsl_lot TO IDX_8F370E19A8CBA5F7');
        $this->addSql('ALTER TABLE transfert_stock_ligne RENAME INDEX fk_tsl_conditionnement TO IDX_8F370E19A222637');
        $this->addSql('ALTER TABLE usine CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE usine RENAME INDEX code TO UNIQ_F3AB48177153098');
        $this->addSql('ALTER TABLE usine RENAME INDEX fk_usine_pays TO IDX_F3AB481A6E44244');
        $this->addSql('ALTER TABLE usine RENAME INDEX fk_usine_region TO IDX_F3AB48198260155');
        $this->addSql('ALTER TABLE usine RENAME INDEX fk_usine_ville TO IDX_F3AB481A73F0036');
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY `fk_utilisateur_pays`');
        $this->addSql('ALTER TABLE utilisateur CHANGE actif actif TINYINT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B3A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id)');
        $this->addSql('ALTER TABLE utilisateur RENAME INDEX email TO UNIQ_1D1C63B3E7927C74');
        $this->addSql('ALTER TABLE utilisateur RENAME INDEX username TO UNIQ_1D1C63B3F85E0677');
        $this->addSql('ALTER TABLE utilisateur RENAME INDEX fk_utilisateur_pays TO IDX_1D1C63B3A6E44244');
        $this->addSql('ALTER TABLE utilisateur_role RENAME INDEX fk_ur_role TO IDX_9EE8E650D60322AC');
        $this->addSql('ALTER TABLE ville DROP FOREIGN KEY `fk_ville_region`');
        $this->addSql('ALTER TABLE ville CHANGE type_ville type_ville VARCHAR(30) DEFAULT \'VILLE\' NOT NULL, CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE ville ADD CONSTRAINT FK_43C3D9C398260155 FOREIGN KEY (region_id) REFERENCES region (id)');
        $this->addSql('ALTER TABLE ville RENAME INDEX idx_ville_region TO IDX_43C3D9C398260155');
        $this->addSql('ALTER TABLE zone_commerciale DROP FOREIGN KEY `fk_zone_ville`');
        $this->addSql('ALTER TABLE zone_commerciale CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE zone_commerciale ADD CONSTRAINT FK_7EE8A42DA73F0036 FOREIGN KEY (ville_id) REFERENCES ville (id)');
        $this->addSql('ALTER TABLE zone_commerciale RENAME INDEX idx_zone_ville TO IDX_7EE8A42DA73F0036');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restaurez la sauvegarde de la base pour revenir avant l\'alignement.');
    }
}
