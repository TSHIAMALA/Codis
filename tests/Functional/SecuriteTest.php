<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Tests\AppWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SecuriteTest extends AppWebTestCase
{
    public static function pagesProtegees(): iterable
    {
        foreach (['/', '/dashboard', '/commandes', '/consignations', '/livraisons', '/paiements', '/utilisateurs', '/tarifs', '/audit'] as $page) {
            yield $page => [$page];
        }
    }

    #[DataProvider('pagesProtegees')]
    public function testVisiteurAnonymeRenvoyeAuLogin(string $page): void
    {
        $this->client->request('GET', $page);

        self::assertResponseRedirects('/login');
    }

    public function testApiExigeUnJeton(): void
    {
        $this->client->request('GET', '/api/commandes');

        self::assertResponseStatusCodeSame(401);
    }

    public function testPostSansJetonCsrfRefuse(): void
    {
        $this->loginAs(AppFixtures::ADMIN);

        $this->client->request('POST', '/consignations/nouveau', ['dr_id' => 1, 'quantite' => 10]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'formulaire n\'est plus valide');
    }

    public function testConnexionAvecMauvaisMotDePasse(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se Connecter')->form([
            'username' => AppFixtures::ADMIN,
            'password' => 'mauvais',
        ]));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Identifiants invalides');
    }

    public function testCompteEnAttenteNePeutPasSeConnecter(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se Connecter')->form([
            'username' => AppFixtures::EN_ATTENTE,
            'password' => AppFixtures::MOT_DE_PASSE,
        ]));
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'en attente de validation');
    }

    public function testConnexionReussie(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se Connecter')->form([
            'username' => AppFixtures::DR,
            'password' => AppFixtures::MOT_DE_PASSE,
        ]));

        self::assertResponseRedirects('/dashboard');
    }

    public static function accesParRole(): iterable
    {
        // [utilisateur, page, code HTTP attendu]
        yield 'agent : commandes' => [AppFixtures::DR, '/commandes', 200];
        yield 'agent : livraisons' => [AppFixtures::DR, '/livraisons', 403];
        yield 'agent : paiements' => [AppFixtures::DR, '/paiements', 403];
        yield 'agent : tarifs' => [AppFixtures::DR, '/tarifs', 403];
        yield 'agent : utilisateurs' => [AppFixtures::DR, '/utilisateurs', 403];
        yield 'dépôt : livraisons' => [AppFixtures::GESTIONNAIRE, '/livraisons', 200];
        yield 'dépôt : commandes' => [AppFixtures::GESTIONNAIRE, '/commandes', 403];
        yield 'caisse : paiements' => [AppFixtures::CAISSIER, '/paiements', 200];
        yield 'caisse : livraisons' => [AppFixtures::CAISSIER, '/livraisons', 403];
        yield 'délégué : réseau commercial' => [AppFixtures::DELEGUE, '/commercial', 200];
        yield 'délégué : audit' => [AppFixtures::DELEGUE, '/audit', 403];
        yield 'partenaire : tableau de bord' => [AppFixtures::PARTENAIRE, '/dashboard', 403];
        yield 'admin : audit' => [AppFixtures::ADMIN, '/audit', 200];
    }

    #[DataProvider('accesParRole')]
    public function testAccesParRole(string $username, string $page, int $code): void
    {
        $this->loginAs($username);
        $this->client->request('GET', $page);

        self::assertResponseStatusCodeSame($code);
        if ($code === 403) {
            self::assertSelectorTextContains('h2', 'Vous n\'avez pas accès à cette page');
        }
    }

    public function testMenuFiltreSelonLeRole(): void
    {
        $this->loginAs(AppFixtures::DR);
        $this->client->request('GET', '/dashboard');

        self::assertSelectorTextContains('aside', 'Commandes Clients');
        self::assertSelectorTextNotContains('aside', 'Livraisons');
        self::assertSelectorTextNotContains('aside', 'Administration');
    }

    public function testDemandeAccesCreeUnCompteInactifSansRole(): void
    {
        $crawler = $this->client->request('GET', '/inscription');
        $this->client->submit($crawler->selectButton('Envoyer ma demande')->form([
            'nom' => 'NOUVEAU',
            'username' => 'nouvel_agent',
            'password' => 'MotDePasse123',
        ]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Demande d\'accès envoyée');

        $u = $this->em()->getRepository(Utilisateur::class)->findOneBy(['username' => 'nouvel_agent']);
        self::assertFalse($u->isActif());
        self::assertCount(0, $u->getRolesEntities());
    }

    public function testAdminPaysNePeutPasAttribuerSuperAdmin(): void
    {
        $adminPays = $this->em()->getRepository(Role::class)->findOneBy(['code' => 'ADMIN_PAYS']);
        $delegue = $this->em()->getRepository(Utilisateur::class)->findOneBy(['username' => AppFixtures::DELEGUE]);
        $delegue->getRolesEntities()->clear();
        $delegue->addRoleEntity($adminPays);
        $this->em()->flush();

        $this->loginAs(AppFixtures::DELEGUE);
        $superAdmin = $this->em()->getRepository(Role::class)->findOneBy(['code' => 'SUPER_ADMIN']);
        $cible = $this->em()->getRepository(Utilisateur::class)->findOneBy(['username' => AppFixtures::CAISSIER]);

        $message = $this->poster('/utilisateurs/' . $cible->getId() . '/modifier', ['nom' => 'X', 'actif' => '1', 'role_id' => $superAdmin->getId()], '/utilisateurs');

        self::assertStringContainsString('pas autorisé à attribuer ce rôle', $message);
        $this->em()->clear();
        $cible = $this->em()->getRepository(Utilisateur::class)->find($cible->getId());
        self::assertNotContains('ROLE_SUPER_ADMIN', $cible->getRoles());
    }
}
