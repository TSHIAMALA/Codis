<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Commande;
use App\Tests\AppWebTestCase;

/**
 * API de l'application mobile (JWT).
 */
class ApiTest extends AppWebTestCase
{
    public function testLoginRenvoieJetonEtProfil(): void
    {
        $data = $this->login(AppFixtures::DR);

        self::assertNotEmpty($data['token']);
        self::assertSame(AppFixtures::DR, $data['user']['username']);
        self::assertSame('DR', $data['user']['typeAgent']);
        self::assertNotNull($data['user']['agentCommercialId']);
        self::assertContains('ROLE_AGENT_COMMERCIAL', $data['user']['roles']);
    }

    public function testLoginRefuseMauvaisMotDePasseEtCompteEnAttente(): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['username' => AppFixtures::DR, 'password' => 'faux']);
        self::assertResponseStatusCodeSame(401);

        $this->client->jsonRequest('POST', '/api/login', ['username' => AppFixtures::EN_ATTENTE, 'password' => AppFixtures::MOT_DE_PASSE]);
        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('en attente de validation', $this->client->getResponse()->getContent());
    }

    public function testJetonFalsifieRefuse(): void
    {
        $jeton = $this->login(AppFixtures::DR)['token'];

        $this->client->request('GET', '/api/commandes', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jeton . 'x']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreationCommandeIgnorePrixEtAuteurEnvoyes(): void
    {
        $jeton = $this->login(AppFixtures::DR)['token'];
        $admin = $this->em()->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['username' => AppFixtures::ADMIN]);

        $this->client->jsonRequest('POST', '/api/commandes/creer', [
            'typeCommande' => 'GROSSISTE',
            'grossiste_id' => $this->grossiste(AppFixtures::DISTRIBUTEUR_AGREE)->getId(),
            'depot_id' => $this->depot()->getId(),
            'user_id' => $admin->getId(),
            'lignes' => [[
                'produit_id' => $this->produit(AppFixtures::PRODUIT_A)->getId(),
                'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_A)->getId(),
                'quantite' => 2,
                'prixUnitaire' => 1,
            ]],
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jeton]);

        self::assertResponseStatusCodeSame(201);
        $reponse = json_decode($this->client->getResponse()->getContent(), true);
        self::assertEquals(900, $reponse['montantTotal']);

        $commande = $this->em()->getRepository(Commande::class)->find($reponse['id']);
        self::assertSame(AppFixtures::DR, $commande->getCreatedBy()->getUsername());
    }

    public function testCommandeSansTarifRefusee(): void
    {
        $jeton = $this->login(AppFixtures::DR)['token'];

        $this->client->jsonRequest('POST', '/api/commandes/creer', [
            'typeCommande' => 'GROSSISTE',
            'grossiste_id' => $this->grossiste(AppFixtures::DISTRIBUTEUR_AGREE)->getId(),
            'lignes' => [['produit_id' => $this->produit(AppFixtures::PRODUIT_B)->getId(), 'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_B)->getId(), 'quantite' => 1]],
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jeton]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Produit B', $this->client->getResponse()->getContent());
    }

    public function testUnAgentNeVoitQueSesCommandes(): void
    {
        // Une commande saisie par un autre agent (le superviseur)
        $jetonSup = $this->login(AppFixtures::SUPERVISEUR)['token'];
        $this->client->jsonRequest('POST', '/api/commandes/creer', [
            'typeCommande' => 'GROSSISTE',
            'grossiste_id' => $this->grossiste(AppFixtures::DISTRIBUTEUR_AGREE)->getId(),
            'lignes' => [['produit_id' => $this->produit(AppFixtures::PRODUIT_A)->getId(), 'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_A)->getId(), 'quantite' => 1]],
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jetonSup]);
        self::assertResponseStatusCodeSame(201);

        $jetonDr = $this->login(AppFixtures::DR)['token'];
        $this->client->request('GET', '/api/commandes?agent_id=999', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jetonDr]);

        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testConfirmationLivraisonNonExpedieeRefusee(): void
    {
        $this->loginAs(AppFixtures::ADMIN);
        $commande = $this->creerCommande(AppFixtures::GROSSISTE_SECTEUR, AppFixtures::PRODUIT_A, 1);
        $this->poster('/commandes/' . $commande->getId() . '/valider', [], '/commandes');
        $this->em()->clear();
        $bl = $this->em()->getRepository(\App\Entity\Livraison::class)->findOneBy(['commande' => $commande->getId()]);

        $jeton = $this->login(AppFixtures::GESTIONNAIRE)['token'];
        $this->client->request('POST', '/api/livraisons/' . $bl->getId() . '/confirmer', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jeton]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testCatalogueAfficheLePrixFacture(): void
    {
        // La DR est en région Kinshasa ; l'app saisit des commandes de détaillants : 480 $ le carton
        $jeton = $this->login(AppFixtures::DR)['token'];
        $this->client->request('GET', '/api/produits', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $jeton]);

        $produits = json_decode($this->client->getResponse()->getContent(), true);
        $produitA = array_values(array_filter($produits, fn ($p) => $p['code'] === AppFixtures::PRODUIT_A))[0];
        $carton = array_values(array_filter($produitA['conditionnements'], fn ($c) => $c['libelle'] === 'Carton'))[0];
        self::assertEquals(480, $carton['prix']);
        self::assertSame('Tarif Région : Kinshasa', $carton['prixSource']);
    }

    public function testCommandeMobileSansDepotEstLivrable(): void
    {
        $jeton = $this->login(AppFixtures::DR)['token'];
        $detaillant = $this->em()->getRepository(\App\Entity\Detailant::class)->findOneBy(['code' => AppFixtures::DETAILLANT]);

        // Charge utile de l'application mobile : pas de dépôt
        $this->client->jsonRequest('POST', '/api/commandes/creer', [
            'typeCommande' => 'DETAILLANT',
            'detailant_id' => $detaillant->getId(),
            'lignes' => [['produit_id' => $this->produit(AppFixtures::PRODUIT_A)->getId(), 'conditionnement_id' => $this->carton(AppFixtures::PRODUIT_A)->getId(), 'quantite' => 2, 'prixUnitaire' => 480]],
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jeton]);

        self::assertResponseStatusCodeSame(201);
        $commande = $this->em()->getRepository(Commande::class)->find(json_decode($this->client->getResponse()->getContent(), true)['id']);
        self::assertSame(AppFixtures::DEPOT, $commande->getDepot()->getCode(), 'Dépôt de la région de l\'agent');
        self::assertSame('960.00', $commande->getMontantTotal());
    }

    private function login(string $username): array
    {
        $this->client->jsonRequest('POST', '/api/login', ['username' => $username, 'password' => AppFixtures::MOT_DE_PASSE]);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
