<?php

namespace App\Tests\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Conditionnement;
use App\Entity\Depot;
use App\Entity\EmplacementStock;
use App\Entity\Produit;
use App\Exception\OperationImpossibleException;
use App\Service\StockManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StockManagerServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StockManagerService $stock;
    private EmplacementStock $depot;
    private Produit $produit;
    private Conditionnement $carton;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->stock = static::getContainer()->get(StockManagerService::class);
        $this->depot = $this->stock->getEmplacementDepot($this->em->getRepository(Depot::class)->findOneBy(['code' => AppFixtures::DEPOT]));
        $this->produit = $this->em->getRepository(Produit::class)->findOneBy(['code' => AppFixtures::PRODUIT_A]);
        $this->carton = $this->em->getRepository(Conditionnement::class)->findOneBy(['produit' => $this->produit, 'libelle' => 'Carton']);
    }

    public function testSortieConsommeLesLotsDansLOrdreDePeremption(): void
    {
        $mouvements = $this->em->wrapInTransaction(fn () => $this->stock->sortir('VENTE', $this->produit, $this->carton, 35, $this->depot));

        self::assertCount(2, $mouvements);
        self::assertSame('LOT-A-ANCIEN', $mouvements[0]->getLot()->getNumeroLot());
        self::assertSame('30.000', $mouvements[0]->getQuantite());
        self::assertSame('LOT-A-RECENT', $mouvements[1]->getLot()->getNumeroLot());
        self::assertSame('5.000', $mouvements[1]->getQuantite());
        self::assertSame(95.0, $this->stock->getDisponible($this->depot, $this->produit, $this->carton));
    }

    public function testSortieSuperieureAuStockRefuseeSansRienModifier(): void
    {
        try {
            $this->em->wrapInTransaction(fn () => $this->stock->sortir('VENTE', $this->produit, $this->carton, 131, $this->depot));
            self::fail('Une sortie supérieure au stock doit être refusée');
        } catch (OperationImpossibleException $e) {
            self::assertStringContainsString('131 demandé(s), 130 disponible(s)', $e->getMessage());
        }

        // L'EntityManager est fermé après un rollback : on relit avec un gestionnaire neuf
        static::getContainer()->get('doctrine')->resetManager();
        $em = static::getContainer()->get('doctrine')->getManager();
        $total = (float) $em->createQuery('SELECT SUM(s.quantiteDisponible) FROM App\Entity\Stock s WHERE s.produit = :p AND s.conditionnement = :c')
            ->setParameter('p', $this->produit->getId())->setParameter('c', $this->carton->getId())->getSingleScalarResult();
        self::assertSame(130.0, $total);
    }

    public function testTransfertPuisAnnulationRestaureLesMemesLots(): void
    {
        $usine = $this->em->getRepository(EmplacementStock::class)->findOneBy(['typeEmplacement' => 'USINE']);

        $this->em->wrapInTransaction(fn () => $this->stock->sortir('TRANSFERT', $this->produit, $this->carton, 40, $this->depot, $usine, 'TEST', 1));
        self::assertSame(90.0, $this->stock->getDisponible($this->depot, $this->produit, $this->carton));
        self::assertSame(40.0, $this->stock->getDisponible($usine, $this->produit, $this->carton));

        $inverses = $this->em->wrapInTransaction(fn () => $this->stock->annulerMouvements('TEST', 1, 'RETOUR'));

        self::assertCount(2, $inverses);
        self::assertSame(130.0, $this->stock->getDisponible($this->depot, $this->produit, $this->carton));
        self::assertSame(0.0, $this->stock->getDisponible($usine, $this->produit, $this->carton));
        self::assertSame('TEST_ANNULATION', $inverses[0]->getReferenceType());
    }

    public function testQuantiteNulleRefusee(): void
    {
        $this->expectException(OperationImpossibleException::class);

        $this->em->wrapInTransaction(fn () => $this->stock->sortir('VENTE', $this->produit, $this->carton, 0, $this->depot));
    }
}
