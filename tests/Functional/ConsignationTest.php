<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\AgentCommercial;
use App\Entity\DemandeConsignation;
use App\Entity\EmplacementStock;
use App\Entity\Utilisateur;
use App\Tests\AppWebTestCase;

/**
 * Distribution directe : demande DR ➔ Superviseur ➔ ASM (stock vers le DR) ➔ clôture de journée.
 */
class ConsignationTest extends AppWebTestCase
{
    public function testCircuitCompletAvecDoubleValidation(): void
    {
        $empDepot = $this->emplacementDepot();
        $demande = $this->creerDemande(50);

        // L'ASM ne peut pas valider avant le Superviseur
        $this->loginAs(AppFixtures::ASM);
        $message = $this->poster('/consignations/' . $demande->getId() . '/valider-asm');
        self::assertStringContainsString('doit d\'abord être approuvée par le Superviseur', $message);

        $this->loginAs(AppFixtures::SUPERVISEUR);
        $message = $this->poster('/consignations/' . $demande->getId() . '/valider-superviseur');
        self::assertStringContainsString('approuvée par le Superviseur', $message);

        // Séparation des tâches : le Superviseur ne valide pas le niveau 2
        $message = $this->poster('/consignations/' . $demande->getId() . '/valider-asm');
        self::assertStringContainsString('Validation impossible', $message);

        $this->loginAs(AppFixtures::ASM);
        $message = $this->poster('/consignations/' . $demande->getId() . '/valider-asm');
        self::assertStringContainsString('placé sur le stock du DR', $message);

        $empDr = $this->emplacementDr();
        self::assertSame(80.0, $this->stockDisponible($empDepot, AppFixtures::PRODUIT_A));
        self::assertSame(50.0, $this->stockDisponible($empDr, AppFixtures::PRODUIT_A));

        $demande = $this->recharger($demande);
        self::assertSame(AppFixtures::SUPERVISEUR, $demande->getSuperviseur()->getUtilisateur()->getUsername());
        self::assertSame(AppFixtures::ASM, $demande->getAsm()->getUtilisateur()->getUsername());

        // Clôture par le DR : 40 vendus (40 × 450 $ = 18 000 $ attendus), 10 invendus
        $this->loginAs(AppFixtures::DR);
        $ligne = $demande->getLignes()->first()->getId();

        $message = $this->poster('/consignations/' . $demande->getId() . '/cloturer', [
            'cashJustifie' => '18000', "quantiteVendue_$ligne" => '40', "quantiteRetournee_$ligne" => '5',
        ]);
        self::assertStringContainsString('doit être égal à la quantité confiée (50)', $message);

        $message = $this->poster('/consignations/' . $demande->getId() . '/cloturer', [
            'cashJustifie' => '17000', "quantiteVendue_$ligne" => '40', "quantiteRetournee_$ligne" => '10',
        ]);
        self::assertStringContainsString('une justification est obligatoire', $message);
        self::assertSame(50.0, $this->stockDisponible($empDr, AppFixtures::PRODUIT_A), 'Une clôture refusée ne déplace aucun stock');

        $message = $this->poster('/consignations/' . $demande->getId() . '/cloturer', [
            'cashJustifie' => '17000', "quantiteVendue_$ligne" => '40', "quantiteRetournee_$ligne" => '10',
            'justificationEcart' => 'Remise accordée au grossiste du marché central',
        ]);
        self::assertStringContainsString('Écart de caisse justifié : -1 000,00 $', $message);

        self::assertSame(90.0, $this->stockDisponible($empDepot, AppFixtures::PRODUIT_A));
        self::assertSame(0.0, $this->stockDisponible($empDr, AppFixtures::PRODUIT_A));
        $demande = $this->recharger($demande);
        self::assertSame('CLOTUREE', $demande->getStatut());
        self::assertSame('17000.00', $demande->getCashJustifie());
        self::assertStringContainsString('Remise accordée', $demande->getObservation());
    }

    public function testValidationAsmRefuseeSiStockInsuffisant(): void
    {
        $demande = $this->creerDemande(1000);
        $this->loginAs(AppFixtures::SUPERVISEUR);
        $this->poster('/consignations/' . $demande->getId() . '/valider-superviseur');

        $this->loginAs(AppFixtures::ASM);
        $message = $this->poster('/consignations/' . $demande->getId() . '/valider-asm');

        self::assertStringContainsString('aucun stock n\'a été sorti', $message);
        self::assertStringContainsString('1 000 demandé(s), 130 disponible(s)', $message);
        self::assertSame(130.0, $this->stockDisponible($this->emplacementDepot(), AppFixtures::PRODUIT_A));
        self::assertSame('VALIDEE_SUPERVISEUR', $this->recharger($demande)->getStatut());
    }

    public function testRejetAvecMotif(): void
    {
        $demande = $this->creerDemande(10);
        $this->loginAs(AppFixtures::SUPERVISEUR);

        $message = $this->poster('/consignations/' . $demande->getId() . '/rejeter', ['motif' => '']);
        self::assertStringContainsString('Indiquez le motif', $message);

        $message = $this->poster('/consignations/' . $demande->getId() . '/rejeter', ['motif' => 'Secteur déjà couvert']);
        self::assertStringContainsString('rejetée', $message);
        $demande = $this->recharger($demande);
        self::assertSame('REJETEE', $demande->getStatut());
        self::assertStringContainsString('Secteur déjà couvert', $demande->getObservation());
    }

    public function testUnDrNeDemandeQuePourLuiMeme(): void
    {
        $this->loginAs(AppFixtures::DR);
        $autreAgent = $this->agent(AppFixtures::SUPERVISEUR);

        $this->poster('/consignations/nouveau', [
            'dr_id' => $autreAgent->getId(),
            'depot_id' => $this->depot()->getId(),
            'produit_id' => $this->produit(AppFixtures::PRODUIT_A)->getId(),
            'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_A)->getId(),
            'quantite' => '5',
        ]);

        $demande = $this->derniereDemande();
        self::assertSame(AppFixtures::DR, $demande->getDr()->getUtilisateur()->getUsername());
    }

    private function creerDemande(float $quantite): DemandeConsignation
    {
        $this->loginAs(AppFixtures::DR);
        $message = $this->poster('/consignations/nouveau', [
            'dr_id' => $this->agent(AppFixtures::DR)->getId(),
            'depot_id' => $this->depot()->getId(),
            'produit_id' => $this->produit(AppFixtures::PRODUIT_A)->getId(),
            'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_A)->getId(),
            'quantite' => (string) $quantite,
        ]);
        self::assertStringContainsString('Demande de prélèvement', $message);

        return $this->derniereDemande();
    }

    private function agent(string $username): AgentCommercial
    {
        $user = $this->em()->getRepository(Utilisateur::class)->findOneBy(['username' => $username]);

        return $this->em()->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $user]);
    }

    private function emplacementDr(): EmplacementStock
    {
        return $this->em()->getRepository(EmplacementStock::class)->findOneBy(['agent' => $this->agent(AppFixtures::DR), 'typeEmplacement' => 'DR']);
    }

    private function derniereDemande(): DemandeConsignation
    {
        $this->em()->clear();

        return $this->em()->getRepository(DemandeConsignation::class)->findOneBy([], ['id' => 'DESC']);
    }

    private function recharger(DemandeConsignation $d): DemandeConsignation
    {
        $this->em()->clear();

        return $this->em()->getRepository(DemandeConsignation::class)->find($d->getId());
    }
}
