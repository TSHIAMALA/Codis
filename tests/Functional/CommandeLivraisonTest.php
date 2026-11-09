<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Commande;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Tests\AppWebTestCase;

/**
 * Distribution indirecte : commande ➔ validation ➔ (paiement) ➔ expédition ➔ réception, et annulations.
 */
class CommandeLivraisonTest extends AppWebTestCase
{
    public function testLePrixEnvoyeParLeNavigateurEstIgnore(): void
    {
        $this->loginAs(AppFixtures::DR);

        $commande = $this->creerCommande(AppFixtures::DISTRIBUTEUR_AGREE, AppFixtures::PRODUIT_A, 2);

        // Tarif région Kinshasa (450 $) prioritaire sur le tarif national (500 $) ; 0,01 $ envoyé ignoré
        self::assertSame('450.00', $commande->getLignes()->first()->getPrixUnitaire());
        self::assertSame('900.00', $commande->getMontantTotal());
        self::assertSame(AppFixtures::DR, $commande->getCreatedBy()->getUsername());
        self::assertNotNull($commande->getAgent(), 'La commande est rattachée à l\'agent connecté');
    }

    public function testCommandeRefuseeSansTarif(): void
    {
        $this->loginAs(AppFixtures::DR);

        $avant = $this->em()->getRepository(Commande::class)->count([]);
        $this->creerCommande(AppFixtures::DISTRIBUTEUR_AGREE, AppFixtures::PRODUIT_B, 1);

        self::assertSelectorTextContains('.alert-danger', 'aucun tarif actif pour Produit B');
        self::assertSame($avant, $this->em()->getRepository(Commande::class)->count([]));
    }

    public function testCircuitCompletDistributeurAgree(): void
    {
        $empDepot = $this->emplacementDepot();
        $this->loginAs(AppFixtures::ADMIN);
        $commande = $this->creerCommande(AppFixtures::DISTRIBUTEUR_AGREE, AppFixtures::PRODUIT_A, 40);

        $message = $this->poster('/commandes/' . $commande->getId() . '/valider', [], '/commandes');
        self::assertStringContainsString('Bon de livraison généré', $message);
        $bl = $this->livraisonActive($commande);

        // Paiement préalable obligatoire
        $message = $this->poster('/livraisons/' . $bl->getId() . '/expedier', [], '/livraisons');
        self::assertStringContainsString('versement du cash à la caisse est obligatoire', $message);
        self::assertSame(130.0, $this->stockDisponible($empDepot, AppFixtures::PRODUIT_A));

        $this->poster('/paiements/nouveau', ['commande_id' => $commande->getId(), 'montant' => '18000', 'modePaiement' => 'ESPECES'], '/paiements');
        $message = $this->poster('/livraisons/' . $bl->getId() . '/expedier', [], '/livraisons');
        self::assertStringContainsString('stock sorti du dépôt', $message);

        // FEFO : les 30 cartons du lot qui périme en premier sont consommés avant le lot récent
        self::assertSame(90.0, $this->stockDisponible($empDepot, AppFixtures::PRODUIT_A));
        $lots = array_map(fn (MouvementStock $m) => [$m->getLot()->getNumeroLot(), (float) $m->getQuantite()],
            $this->em()->getRepository(MouvementStock::class)->findBy(['referenceType' => 'LIVRAISON', 'referenceId' => $bl->getId()], ['id' => 'ASC']));
        self::assertSame([['LOT-A-ANCIEN', 30.0], ['LOT-A-RECENT', 10.0]], $lots);
        self::assertSame('EXPEDIEE', $this->recharger($commande)->getStatut());

        $this->poster('/livraisons/' . $bl->getId() . '/confirmer', [], '/livraisons');
        self::assertSame('RECEPTIONNEE', $this->recharger($commande)->getStatut());

        // Plus aucune transition possible après réception : bouton absent, et requête forcée refusée
        $this->client->request('GET', '/livraisons');
        self::assertSelectorNotExists('button[onclick="openModal(\'modalCancelLivraison' . $bl->getId() . '\')"]');
        $message = $this->poster('/livraisons/' . $bl->getId() . '/annuler', [], '/commandes');
        self::assertStringContainsString('ne peut plus être annulé', $message);
    }

    public function testAnnulationDuBlEnTransitReintegreLeStock(): void
    {
        $empDepot = $this->emplacementDepot();
        $this->loginAs(AppFixtures::ADMIN);
        $commande = $this->creerCommande(AppFixtures::GROSSISTE_SECTEUR, AppFixtures::PRODUIT_A, 1);
        $this->poster('/commandes/' . $commande->getId() . '/valider', [], '/commandes');
        $bl = $this->livraisonActive($commande);
        $this->poster('/livraisons/' . $bl->getId() . '/expedier', [], '/livraisons');
        self::assertSame(129.0, $this->stockDisponible($empDepot, AppFixtures::PRODUIT_A));

        // La commande ne peut pas être annulée tant que son BL est en transit
        $message = $this->poster('/commandes/' . $commande->getId() . '/annuler', [], '/commandes');
        self::assertStringContainsString('annulez d\'abord le bon de livraison', $message);

        $message = $this->poster('/livraisons/' . $bl->getId() . '/annuler', [], '/livraisons');
        self::assertStringContainsString('réintégrée au stock du dépôt', $message);
        self::assertSame(130.0, $this->stockDisponible($empDepot, AppFixtures::PRODUIT_A));
        self::assertSame('VALIDEE', $this->recharger($commande)->getStatut());

        // Un nouveau BL peut être généré puis la commande annulée (BL non expédié annulé avec elle)
        $message = $this->poster('/commandes/' . $commande->getId() . '/nouveau-bl', [], '/commandes');
        self::assertStringContainsString('Nouveau bon de livraison', $message);
        $this->poster('/commandes/' . $commande->getId() . '/annuler', [], '/commandes');
        $commande = $this->recharger($commande);
        self::assertSame('ANNULEE', $commande->getStatut());
        foreach ($commande->getLivraisons() as $l) {
            self::assertSame('ANNULEE', $l->getStatut());
        }
    }

    public function testExpeditionRefuseeSiStockInsuffisant(): void
    {
        $empDepot = $this->emplacementDepot();
        $this->loginAs(AppFixtures::ADMIN);
        $grossiste = $this->grossiste(AppFixtures::GROSSISTE_SECTEUR);
        $grossiste->setLimiteCredit('0');
        $this->em()->flush();

        $commande = $this->creerCommande(AppFixtures::GROSSISTE_SECTEUR, AppFixtures::PRODUIT_A, 500);
        $this->poster('/commandes/' . $commande->getId() . '/valider', [], '/commandes');
        $bl = $this->livraisonActive($commande);

        $this->client->request('GET', '/livraisons');
        self::assertSelectorTextContains('body', 'Stock insuffisant');

        $message = $this->poster('/livraisons/' . $bl->getId() . '/expedier', [], '/livraisons');
        self::assertStringContainsString('500 demandé(s), 130 disponible(s)', $message);
        self::assertSame(130.0, $this->stockDisponible($empDepot, AppFixtures::PRODUIT_A));
        self::assertSame('PREPARATION', $this->recharger($bl)->getStatut());
    }

    public function testLimiteDeCreditBloqueLaValidation(): void
    {
        // Grossiste secteur : limite 1 000 $ ; 3 cartons à 450 $ = 1 350 $
        $this->loginAs(AppFixtures::DELEGUE);
        $commande = $this->creerCommande(AppFixtures::GROSSISTE_SECTEUR, AppFixtures::PRODUIT_A, 3);

        $this->client->request('GET', '/commandes/' . $commande->getId());
        self::assertSelectorTextContains('body', 'Limite de crédit dépassée');

        $message = $this->poster('/commandes/' . $commande->getId() . '/valider', [], '/commandes');
        self::assertStringContainsString('Limite de crédit dépassée pour Grossiste Secteur Test', $message);
        self::assertSame('SOUMISE', $this->recharger($commande)->getStatut());
    }

    public function testUnAgentNePeutPasValiderSaCommande(): void
    {
        $this->loginAs(AppFixtures::DR);
        $commande = $this->creerCommande(AppFixtures::DISTRIBUTEUR_AGREE, AppFixtures::PRODUIT_A, 1);

        $this->client->request('GET', '/commandes');
        $jeton = $this->client->getCrawler()->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/commandes/' . $commande->getId() . '/valider', ['_token' => $jeton]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPaiementNulRefuse(): void
    {
        $this->loginAs(AppFixtures::ADMIN);
        $commande = $this->creerCommande(AppFixtures::DISTRIBUTEUR_AGREE, AppFixtures::PRODUIT_A, 1);

        $message = $this->poster('/paiements/nouveau', ['commande_id' => $commande->getId(), 'montant' => '0'], '/paiements');

        self::assertStringContainsString('doit être supérieur à zéro', $message);
    }

    private function livraisonActive(Commande $commande): Livraison
    {
        $this->em()->clear();

        return $this->em()->getRepository(Livraison::class)->findOneBy(['commande' => $commande->getId(), 'statut' => 'PREPARATION']);
    }

    /** @template T of object @param T $entite @return T */
    private function recharger(object $entite): object
    {
        $this->em()->clear();

        return $this->em()->getRepository($entite::class)->find($entite->getId());
    }
}
