<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\AgentCommercial;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\DemandeConsignation;
use App\Entity\DemandeConsignationLigne;
use App\Entity\Livraison;
use App\Entity\LivraisonLigne;
use App\Entity\Paiement;
use App\Entity\Utilisateur;
use App\Service\StockManagerService;
use App\Tests\AppWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Garde-fous de performance : le nombre de requêtes SQL d'une liste ne doit pas dépendre du nombre
 * d'éléments (pas de requête N+1), et les listes sont paginées.
 */
class PerformanceTest extends AppWebTestCase
{
    public static function listes(): iterable
    {
        yield 'tableau de bord' => ['/dashboard'];
        yield 'commandes' => ['/commandes'];
        yield 'livraisons' => ['/livraisons'];
        yield 'paiements' => ['/paiements'];
        yield 'consignations' => ['/consignations'];
        yield 'mouvements de stock' => ['/stocks?tab=mouvements'];
        yield 'détail des stocks' => ['/stocks?tab=detail'];
        yield 'tarifs' => ['/tarifs'];
        yield 'audit' => ['/audit'];
    }

    #[DataProvider('listes')]
    public function testNombreDeRequetesIndependantDuVolume(string $page): void
    {
        // On compare deux listes non vides (5 puis 30 éléments) : une liste vide économise des requêtes fixes
        $this->creerDonnees(5);
        $this->loginAs(AppFixtures::ADMIN);
        $avant = $this->compterRequetes($page);

        $this->creerDonnees(25, 6);
        $apres = $this->compterRequetes($page);

        self::assertSame($avant, $apres, sprintf('%s : %d requêtes avec 5 éléments, %d avec 30 (requêtes N+1 ?)', $page, $avant, $apres));
    }

    public function testListeDesCommandesPagineeEtFiltrable(): void
    {
        $this->creerDonnees(30);
        $this->loginAs(AppFixtures::ADMIN);

        $crawler = $this->client->request('GET', '/commandes');
        self::assertCount(25, $crawler->filter('table.custom-table tbody tr'));
        self::assertSelectorTextContains('.pagination-info', '1–25 sur 30');

        $crawler = $this->client->request('GET', '/commandes?page=2');
        self::assertCount(5, $crawler->filter('table.custom-table tbody tr'));

        // Page au-delà de la dernière : la dernière page est affichée
        $this->client->request('GET', '/commandes?page=99');
        self::assertSelectorTextContains('.pagination-info', '26–30 sur 30');

        $crawler = $this->client->request('GET', '/commandes?q=PERF-007');
        self::assertCount(1, $crawler->filter('table.custom-table tbody tr'));

        $crawler = $this->client->request('GET', '/commandes?statut=ANNULEE');
        self::assertSelectorTextContains('table.custom-table tbody', 'Aucune commande ne correspond aux filtres');

        // Les liens de pagination conservent les filtres
        $this->client->request('GET', '/commandes?statut=SOUMISE');
        self::assertSelectorExists('a.pagination-lien[href*="statut=SOUMISE"][href*="page=2"]');
    }

    private function compterRequetes(string $page): int
    {
        // Requête d'échauffement : la première requête d'un client charge la session et l'utilisateur
        $this->client->request('GET', $page);
        $this->em()->clear();
        $this->client->enableProfiler();
        $this->client->request('GET', $page);
        self::assertResponseIsSuccessful();

        return $this->client->getProfile()->getCollector('db')->getQueryCount();
    }

    /** Ajoute $n commandes (ligne, BL, paiement), demandes de consignation et mouvements de stock. */
    private function creerDonnees(int $n, int $premier = 1): void
    {
        $em = $this->em();
        $produit = $this->produit(AppFixtures::PRODUIT_A);
        $carton = $this->carton(AppFixtures::PRODUIT_A);
        $depot = $this->depot();
        $grossiste = $this->grossiste(AppFixtures::DISTRIBUTEUR_AGREE);
        $user = $em->getRepository(Utilisateur::class)->findOneBy(['username' => AppFixtures::DR]);
        $dr = $em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $user]);
        $stock = static::getContainer()->get(StockManagerService::class);
        $empDepot = $stock->getEmplacementDepot($depot);

        for ($i = $premier; $i < $premier + $n; $i++) {
            $num = sprintf('PERF-%03d', $i);
            $c = (new Commande())->setNumero($num)->setTypeCommande('GROSSISTE')->setGrossiste($grossiste)->setDepot($depot)
                ->setDateCommande(new \DateTime("-$i hours"))->setStatut('SOUMISE')->setMontantTotal('450.00')->setCreatedBy($user);
            $em->persist($c);
            $em->persist((new CommandeLigne())->setCommande($c)->setProduit($produit)->setConditionnement($carton)->setQuantite('1')->setPrixUnitaire('450')->setMontant('450'));

            $l = (new Livraison())->setNumero("BL-$num")->setCommande($c)->setDepot($depot)->setStatut('PREPARATION')->setDatePrevue(new \DateTime());
            $em->persist($l);
            $em->persist((new LivraisonLigne())->setLivraison($l)->setProduit($produit)->setConditionnement($carton)->setQuantitePrevue('1')->setQuantiteLivree('1'));
            $em->persist((new Paiement())->setCommande($c)->setMontant('100')->setModePaiement('ESPECES')->setStatut('VALIDE')->setDatePaiement(new \DateTime())->setUtilisateur($user));

            $d = (new DemandeConsignation())->setNumero("CONS-$num")->setDr($dr)->setDepot($depot)->setDateDemande(new \DateTime())->setStatut('SOUMISE');
            $em->persist($d);
            $em->persist((new DemandeConsignationLigne())->setDemande($d)->setProduit($produit)->setConditionnement($carton)->setQuantiteDemandee('5')->setQuantiteAccordee('5'));
        }
        $em->flush();

        $em->wrapInTransaction(function () use ($stock, $produit, $empDepot, $carton, $n, $premier) {
            for ($i = $premier; $i < $premier + $n; $i++) {
                $stock->entrer('ENTREE', $produit, 1, $empDepot, null, $carton, 'TEST', $i);
            }
        });
        $em->clear();
    }
}
