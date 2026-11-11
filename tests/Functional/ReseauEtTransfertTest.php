<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\AgentCommercial;
use App\Entity\DemandeConsignation;
use App\Entity\EmplacementStock;
use App\Entity\Grossiste;
use App\Entity\MouvementStock;
use App\Entity\Production;
use App\Entity\TransfertStock;
use App\Entity\Utilisateur;
use App\Tests\AppWebTestCase;

/**
 * Hiérarchie commerciale, grossistes, production par conditionnement, transferts entre sites,
 * pertes de tournée DR.
 */
class ReseauEtTransfertTest extends AppWebTestCase
{
    // --- Agents ---

    public function testCreationAgentAvecFonctionEtSuperieur(): void
    {
        $this->loginAs(AppFixtures::DELEGUE);
        $superviseur = $this->agent(AppFixtures::SUPERVISEUR);

        $message = $this->poster('/commercial/agent/nouveau', [
            'nom' => 'NOUVEAU', 'prenom' => 'Dr', 'username' => 'nouveau.dr', 'password' => 'MotDePasse123',
            'typeAgent' => 'DR', 'superviseur_id' => $superviseur->getId(),
        ], '/commercial?tab=agents');
        self::assertStringContainsString('créé(e) avec succès, rattaché(e) à', $message);

        $agent = $this->agent('nouveau.dr');
        self::assertSame('DR', $agent->getTypeAgent());
        self::assertSame($superviseur->getId(), $agent->getSuperviseur()->getId());
        self::assertContains('ROLE_AGENT_COMMERCIAL', $agent->getUtilisateur()->getRoles(), 'Le nouvel agent doit pouvoir accéder aux écrans commerciaux');

        // Il peut se connecter et travailler
        $this->loginAs('nouveau.dr');
        $this->client->request('GET', '/commandes');
        self::assertResponseIsSuccessful();
    }

    public function testSuperieurDeMauvaisNiveauRefuse(): void
    {
        $this->loginAs(AppFixtures::DELEGUE);
        $asm = $this->agent(AppFixtures::ASM);

        // Un DR doit être rattaché à un Superviseur, pas directement à un ASM
        $message = $this->poster('/commercial/agent/nouveau', [
            'nom' => 'X', 'username' => 'dr.mal.rattache', 'password' => 'MotDePasse123', 'typeAgent' => 'DR', 'superviseur_id' => $asm->getId(),
        ], '/commercial?tab=agents');

        self::assertStringContainsString('Le supérieur d\'un DR doit être un SUPERVISEUR actif', $message);
        self::assertNull($this->em()->getRepository(Utilisateur::class)->findOneBy(['username' => 'dr.mal.rattache']));
    }

    public function testCompteExistantDevientAgent(): void
    {
        $this->loginAs(AppFixtures::DELEGUE);
        $caissier = $this->em()->getRepository(Utilisateur::class)->findOneBy(['username' => AppFixtures::CAISSIER]);

        $this->poster('/commercial/agent/nouveau', ['utilisateur_id' => $caissier->getId(), 'typeAgent' => 'ASM'], '/commercial?tab=agents');

        $agent = $this->agent(AppFixtures::CAISSIER);
        self::assertSame('ASM', $agent->getTypeAgent());
        self::assertNull($agent->getSuperviseur());
    }

    public function testChangementDeFonctionBloqueSiSubordonnes(): void
    {
        $this->loginAs(AppFixtures::DELEGUE);
        $superviseur = $this->agent(AppFixtures::SUPERVISEUR);

        // Le superviseur encadre la DR des données de test
        $message = $this->poster('/commercial/agent/' . $superviseur->getId() . '/modifier', ['typeAgent' => 'DR', 'actif' => '1'], '/commercial?tab=agents');

        self::assertStringContainsString('agent(s) actif(s) sont rattachés à cette personne', $message);
        self::assertSame('SUPERVISEUR', $this->agent(AppFixtures::SUPERVISEUR)->getTypeAgent());
    }

    // --- Grossistes ---

    public function testTypeDeDistributionModifiable(): void
    {
        $this->loginAs(AppFixtures::DELEGUE);
        $g = $this->grossiste(AppFixtures::DISTRIBUTEUR_AGREE);
        $dr = $this->agent(AppFixtures::DR);

        $this->poster('/commercial/grossiste/' . $g->getId() . '/modifier', [
            'raisonSociale' => $g->getRaisonSociale(), 'limiteCredit' => '2500', 'statut' => 'ACTIF',
            'typeDistribution' => 'GROSSISTE_SECTEUR', 'dr_id' => $dr->getId(),
        ], '/commercial?tab=grossistes');

        $this->em()->clear();
        $g = $this->em()->getRepository(Grossiste::class)->find($g->getId());
        self::assertSame('GROSSISTE_SECTEUR', $g->getTypeDistribution());
        self::assertSame('2500.00', $g->getLimiteCredit());
        self::assertSame($dr->getId(), $g->getDr()->getId());
    }

    // --- Production ---

    public function testProductionDansLeConditionnementChoisi(): void
    {
        $this->loginAs(AppFixtures::USINE);
        $usine = $this->emplacementUsine();
        $avant = $this->stockDisponible($usine, AppFixtures::PRODUIT_A);

        $this->poster('/production/nouveau', [
            'usine_id' => $usine->getUsine()->getId(),
            'produit_id' => $this->produit(AppFixtures::PRODUIT_A)->getId(),
            'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_A)->getId(),
            'quantite' => '50',
        ], '/production');
        $this->em()->clear();
        $of = $this->em()->getRepository(Production::class)->findOneBy([], ['id' => 'DESC']);
        self::assertSame('Carton', $of->getLignes()->first()->getConditionnement()->getLibelle());

        $this->poster('/production/' . $of->getId() . '/valider', [], '/production');
        self::assertSame($avant + 50, $this->stockDisponible($this->emplacementUsine(), AppFixtures::PRODUIT_A));
    }

    // --- Transferts ---

    public function testTransfertUsineVersDepotEnDeuxTemps(): void
    {
        $usine = $this->emplacementUsine();
        $depot = $this->emplacementDepot();

        $this->loginAs(AppFixtures::USINE);
        $message = $this->expedier(80);
        self::assertStringContainsString('en route de « Stock Usine Test » vers « Stock Dépôt Test »', $message);

        // En transit : sorti de l'usine, pas encore entré au dépôt
        self::assertSame(120.0, $this->stockDisponible($usine, AppFixtures::PRODUIT_A));
        self::assertSame(130.0, $this->stockDisponible($depot, AppFixtures::PRODUIT_A));
        $t = $this->dernierTransfert();
        self::assertSame('EN_TRANSIT', $t->getStatut());
        self::assertSame('LOT-A-RECENT', $t->getLignes()->first()->getLot()->getNumeroLot());

        // Le responsable usine ne réceptionne pas : c'est le dépôt d'arrivée
        $this->client->request('GET', '/stocks?tab=transferts');
        $jeton = $this->client->getCrawler()->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/stocks/transferts/' . $t->getId() . '/receptionner', ['_token' => $jeton]);
        self::assertResponseStatusCodeSame(403);

        $this->loginAs(AppFixtures::GESTIONNAIRE);
        $message = $this->poster('/stocks/transferts/' . $t->getId() . '/receptionner', [], '/stocks?tab=transferts');
        self::assertStringContainsString('réceptionné', $message);
        self::assertSame(210.0, $this->stockDisponible($depot, AppFixtures::PRODUIT_A));
        self::assertSame('RECEPTIONNE', $this->dernierTransfert()->getStatut());
    }

    public function testTransfertAnnuleRetourneAuDepart(): void
    {
        $this->loginAs(AppFixtures::USINE);
        $this->expedier(80);
        $t = $this->dernierTransfert();

        $message = $this->poster('/stocks/transferts/' . $t->getId() . '/annuler', [], '/stocks?tab=transferts');

        self::assertStringContainsString('revenue au site de départ', $message);
        self::assertSame(200.0, $this->stockDisponible($this->emplacementUsine(), AppFixtures::PRODUIT_A));
        self::assertSame('ANNULE', $this->dernierTransfert()->getStatut());
    }

    public function testTransfertSuperieurAuStockRefuse(): void
    {
        $this->loginAs(AppFixtures::USINE);
        $avant = $this->em()->getRepository(TransfertStock::class)->count([]);

        $message = $this->expedier(500);

        self::assertStringContainsString('500 demandé(s), 200 disponible(s)', $message);
        self::assertSame($avant, $this->em()->getRepository(TransfertStock::class)->count([]), 'Aucun transfert ne doit rester enregistré');
        self::assertSame(200.0, $this->stockDisponible($this->emplacementUsine(), AppFixtures::PRODUIT_A));
    }

    public function testUnAgentNePeutPasTransferer(): void
    {
        $this->loginAs(AppFixtures::DR);
        $this->client->request('GET', '/commandes');
        $jeton = $this->client->getCrawler()->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/stocks/transferts/nouveau', ['_token' => $jeton]);

        self::assertResponseStatusCodeSame(403);
    }

    // --- Pertes de tournée ---

    public function testPertesDeclareesEtJustifiees(): void
    {
        $demande = $this->tourneeValidee(50);
        $empDr = $this->em()->getRepository(EmplacementStock::class)->findOneBy(['agent' => $this->agent(AppFixtures::DR), 'typeEmplacement' => 'DR']);
        $ligne = $demande->getLignes()->first()->getId();

        $this->loginAs(AppFixtures::DR);
        $donnees = ['cashJustifie' => '18000', "quantiteVendue_$ligne" => '40', "quantitePerdue_$ligne" => '3', "quantiteRetournee_$ligne" => '7'];

        $message = $this->poster('/consignations/' . $demande->getId() . '/cloturer', $donnees);
        self::assertStringContainsString('une justification est obligatoire', $message);

        $message = $this->poster('/consignations/' . $demande->getId() . '/cloturer', $donnees + ['justificationEcart' => '3 cartons abîmés par la pluie, constat signé']);
        self::assertStringContainsString('Clôture de journée enregistrée', $message);

        // 80 au dépôt après le prélèvement de 50, + 7 rendus ; le stock DR est soldé
        self::assertSame(87.0, $this->stockDisponible($this->emplacementDepot(), AppFixtures::PRODUIT_A));
        self::assertSame(0.0, $this->stockDisponible($empDr, AppFixtures::PRODUIT_A));
        $pertes = $this->em()->getRepository(MouvementStock::class)->findBy(['referenceType' => 'PERTE_TOURNEE_DR', 'referenceId' => $demande->getId()]);
        self::assertSame('AJUSTEMENT', $pertes[0]->getTypeMouvement());
        self::assertSame(3.0, array_sum(array_map(fn ($m) => (float) $m->getQuantite(), $pertes)));
        $this->em()->clear();
        $demande = $this->em()->getRepository(DemandeConsignation::class)->find($demande->getId());
        self::assertSame('3.000', $demande->getLignes()->first()->getQuantitePerdue());
        self::assertStringContainsString('pertes 3', $demande->getObservation());
    }

    public function testPertesComptentDansLeTotalConfie(): void
    {
        $demande = $this->tourneeValidee(50);
        $ligne = $demande->getLignes()->first()->getId();
        $this->loginAs(AppFixtures::DR);

        // 40 + 3 + 10 = 53 ≠ 50
        $message = $this->poster('/consignations/' . $demande->getId() . '/cloturer', [
            'cashJustifie' => '18000', "quantiteVendue_$ligne" => '40', "quantitePerdue_$ligne" => '3', "quantiteRetournee_$ligne" => '10', 'justificationEcart' => 'x',
        ]);

        self::assertStringContainsString('vendu (40) + invendus (10) + pertes (3) doit être égal à la quantité confiée (50)', $message);
    }

    // --- Outils ---

    private function agent(string $username): AgentCommercial
    {
        $this->em()->clear();
        $user = $this->em()->getRepository(Utilisateur::class)->findOneBy(['username' => $username]);

        return $this->em()->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $user]);
    }

    private function emplacementUsine(): EmplacementStock
    {
        return $this->em()->getRepository(EmplacementStock::class)->findOneBy(['typeEmplacement' => 'USINE']);
    }

    private function expedier(float $quantite): string
    {
        return $this->poster('/stocks/transferts/nouveau', [
            'source_id' => $this->emplacementUsine()->getId(),
            'destination_id' => $this->emplacementDepot()->getId(),
            'produit_id' => $this->produit(AppFixtures::PRODUIT_A)->getId(),
            'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_A)->getId(),
            'quantite' => (string) $quantite,
        ], '/stocks?tab=transferts');
    }

    private function dernierTransfert(): TransfertStock
    {
        $this->em()->clear();

        return $this->em()->getRepository(TransfertStock::class)->findOneBy([], ['id' => 'DESC']);
    }

    /** Demande de prélèvement de la DR, validée par le Superviseur puis l'ASM (stock confié au DR). */
    private function tourneeValidee(float $quantite): DemandeConsignation
    {
        $this->loginAs(AppFixtures::DR);
        $this->poster('/consignations/nouveau', [
            'dr_id' => $this->agent(AppFixtures::DR)->getId(),
            'depot_id' => $this->depot()->getId(),
            'produit_id' => $this->produit(AppFixtures::PRODUIT_A)->getId(),
            'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_A)->getId(),
            'quantite' => (string) $quantite,
        ]);
        $this->em()->clear();
        $demande = $this->em()->getRepository(DemandeConsignation::class)->findOneBy([], ['id' => 'DESC']);

        $this->loginAs(AppFixtures::SUPERVISEUR);
        $this->poster('/consignations/' . $demande->getId() . '/valider-superviseur');
        $this->loginAs(AppFixtures::ASM);
        $this->poster('/consignations/' . $demande->getId() . '/valider-asm');

        $this->em()->clear();

        return $this->em()->getRepository(DemandeConsignation::class)->find($demande->getId());
    }
}
