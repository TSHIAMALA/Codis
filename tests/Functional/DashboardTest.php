<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Commande;
use App\Entity\Livraison;
use App\Tests\AppWebTestCase;

class DashboardTest extends AppWebTestCase
{
    public function testCartesRenvoientVersLeDetail(): void
    {
        $this->loginAs(AppFixtures::ADMIN);
        $crawler = $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        $liens = $crawler->filter('a.card-stat-link')->each(fn ($a) => $a->attr('href'));
        self::assertContains('/commandes?statut=CHIFFRE_AFFAIRES', $liens);
        self::assertContains('/stocks?tab=synthese', $liens);
        self::assertContains('/commercial?tab=grossistes', $liens);
        self::assertContains('/livraisons?statut=EN_COURS', $liens);
    }

    public function testPasDeLienVersUnePageInterdite(): void
    {
        // Le caissier voit le tableau de bord, mais n'a accès ni aux livraisons, ni aux commandes, ni au réseau
        $this->loginAs(AppFixtures::CAISSIER);
        $crawler = $this->client->request('GET', '/dashboard');

        $liens = $crawler->filter('a.card-stat-link')->each(fn ($a) => $a->attr('href'));
        self::assertSame(['/stocks?tab=synthese'], $liens);
    }

    public function testFiltresGroupesCorrespondentAuxCompteurs(): void
    {
        $em = $this->em();
        $this->loginAs(AppFixtures::ADMIN);
        $this->creerCommande(AppFixtures::DISTRIBUTEUR_AGREE, AppFixtures::PRODUIT_A, 5);

        $livraisonsEnCours = $em->getRepository(Livraison::class)->count(['statut' => Livraison::STATUTS_EN_COURS]);
        $commandesCa = $em->getRepository(Commande::class)->count(['statut' => Commande::STATUTS_CHIFFRE_AFFAIRES]);

        $this->client->request('GET', '/livraisons?statut=EN_COURS');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.card-table-header .badge', $livraisonsEnCours . ' expéditions');

        $this->client->request('GET', '/commandes?statut=CHIFFRE_AFFAIRES');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.card-table-header .badge', $commandesCa . ' commandes');
    }
}
