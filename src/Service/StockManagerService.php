<?php

namespace App\Service;

use App\Entity\Stock;
use App\Entity\MouvementStock;
use App\Entity\EmplacementStock;
use App\Entity\Produit;
use App\Entity\Conditionnement;
use App\Entity\Lot;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class StockManagerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $auditService
    ) {}

    public function getOrCreateStock(EmplacementStock $emp, Produit $produit, ?Lot $lot = null, ?Conditionnement $cond = null): Stock
    {
        $criteria = [
            'emplacement' => $emp,
            'produit' => $produit,
        ];
        if ($lot) $criteria['lot'] = $lot;
        if ($cond) $criteria['conditionnement'] = $cond;

        $stock = $this->em->getRepository(Stock::class)->findOneBy($criteria);
        if (!$stock) {
            $stock = new Stock();
            $stock->setEmplacement($emp);
            $stock->setProduit($produit);
            $stock->setLot($lot);
            $stock->setConditionnement($cond);
            $stock->setQuantiteDisponible('0.000');
            $stock->setQuantiteReservee('0.000');
            $stock->setQuantiteTransit('0.000');
            $this->em->persist($stock);
        }
        return $stock;
    }

    public function enregistrerMouvement(
        string $typeMouvement,
        Produit $produit,
        float $quantite,
        ?EmplacementStock $source = null,
        ?EmplacementStock $destination = null,
        ?Lot $lot = null,
        ?Conditionnement $cond = null,
        ?string $refType = null,
        ?int $refId = null,
        ?Utilisateur $user = null,
        ?string $obs = null
    ): MouvementStock {
        $mvt = new MouvementStock();
        $mvt->setTypeMouvement($typeMouvement);
        $mvt->setProduit($produit);
        $mvt->setLot($lot);
        $mvt->setConditionnement($cond);
        $mvt->setEmplacementSource($source);
        $mvt->setEmplacementDestination($destination);
        $mvt->setQuantite((string) $quantite);
        $mvt->setReferenceType($refType);
        $mvt->setReferenceId($refId);
        $mvt->setUtilisateur($user);
        $mvt->setObservation($obs);

        $this->em->persist($mvt);

        // Update Stock balances
        if ($source) {
            $stockSrc = $this->getOrCreateStock($source, $produit, $lot, $cond);
            $dispo = (float) $stockSrc->getQuantiteDisponible() - $quantite;
            $stockSrc->setQuantiteDisponible((string) max(0, $dispo));
            $stockSrc->setUpdatedAt(new \DateTimeImmutable());
        }

        if ($destination) {
            $stockDest = $this->getOrCreateStock($destination, $produit, $lot, $cond);
            $dispo = (float) $stockDest->getQuantiteDisponible() + $quantite;
            $stockDest->setQuantiteDisponible((string) $dispo);
            $stockDest->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->em->flush();
        $this->auditService->log('mouvement_stock', 'CREATE', $mvt->getId(), null, [
            'type' => $typeMouvement,
            'produit' => $produit->getNom(),
            'quantite' => $quantite,
        ]);

        return $mvt;
    }
}