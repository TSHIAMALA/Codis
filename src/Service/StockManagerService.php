<?php

namespace App\Service;

use App\Entity\AgentCommercial;
use App\Entity\Conditionnement;
use App\Entity\Depot;
use App\Entity\EmplacementStock;
use App\Entity\Lot;
use App\Entity\MouvementStock;
use App\Entity\Produit;
use App\Entity\Stock;
use App\Entity\Utilisateur;
use App\Exception\OperationImpossibleException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Seul point d'écriture des soldes de stock.
 *
 * Le stock est tenu par (emplacement, produit, lot, conditionnement). Les sorties consomment les lots
 * dont la péremption est la plus proche (FEFO), verrouillent les lignes concernées (SELECT ... FOR UPDATE)
 * et refusent tout solde négatif : elles doivent donc être appelées dans une transaction
 * ($em->wrapInTransaction(...)) afin qu'une opération multi-lignes soit entièrement appliquée ou annulée.
 */
class StockManagerService
{
    private const EPSILON = 0.0005;

    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $auditService
    ) {}

    public function getEmplacementDepot(?Depot $depot): ?EmplacementStock
    {
        return $depot ? $this->em->getRepository(EmplacementStock::class)->findOneBy(['depot' => $depot, 'typeEmplacement' => 'DEPOT', 'actif' => true]) : null;
    }

    /** Emplacement virtuel du stock confié à un DR en tournée (créé au premier prélèvement). */
    public function getOrCreateEmplacementDr(AgentCommercial $dr): EmplacementStock
    {
        $emp = $this->em->getRepository(EmplacementStock::class)->findOneBy(['agent' => $dr, 'typeEmplacement' => 'DR']);
        if (!$emp) {
            $emp = new EmplacementStock();
            $emp->setTypeEmplacement('DR');
            $emp->setAgent($dr);
            $emp->setNom('Stock DR — ' . ($dr->getUtilisateur()?->getNomComplet() ?? 'Agent #' . $dr->getId()));
            $this->em->persist($emp);
        }

        return $emp;
    }

    public function getDisponible(EmplacementStock $emp, Produit $produit, ?Conditionnement $cond): float
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(s.quantiteDisponible), 0)')
            ->from(Stock::class, 's')
            ->where('s.emplacement = :emp AND s.produit = :produit')
            ->setParameter('emp', $emp)
            ->setParameter('produit', $produit);
        $this->filtrerConditionnement($qb, $cond);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Stock disponible de plusieurs emplacements en une requête (pour les listes).
     *
     * @param EmplacementStock[] $emplacements
     * @return array<string, float> clé "emplacement-produit-conditionnement" (cf. cleDisponible())
     */
    public function getDisponibles(array $emplacements): array
    {
        if (!$emplacements) {
            return [];
        }

        $dispos = [];
        foreach ($this->em->createQueryBuilder()
            ->select('IDENTITY(s.emplacement) AS e', 'IDENTITY(s.produit) AS p', 'IDENTITY(s.conditionnement) AS c', 'SUM(s.quantiteDisponible) AS q')
            ->from(Stock::class, 's')
            ->where('s.emplacement IN (:emps)')
            ->setParameter('emps', array_map(fn (EmplacementStock $e) => $e->getId(), $emplacements))
            ->groupBy('s.emplacement', 's.produit', 's.conditionnement')
            ->getQuery()->getArrayResult() as $ligne) {
            $dispos[$ligne['e'] . '-' . $ligne['p'] . '-' . ($ligne['c'] ?? '')] = (float) $ligne['q'];
        }

        return $dispos;
    }

    public static function cleDisponible(EmplacementStock $emp, Produit $produit, ?Conditionnement $cond): string
    {
        return $emp->getId() . '-' . $produit->getId() . '-' . ($cond?->getId() ?? '');
    }

    /** Entrée en stock (production, réception) sur une ligne précise. */
    public function entrer(
        string $type,
        Produit $produit,
        float $quantite,
        EmplacementStock $destination,
        ?Lot $lot,
        ?Conditionnement $cond,
        ?string $refType = null,
        ?int $refId = null,
        ?Utilisateur $user = null,
        ?string $obs = null
    ): MouvementStock {
        $this->verifierQuantite($quantite);
        $stock = $this->getOrCreateStock($destination, $produit, $lot, $cond, true);
        $this->crediter($stock, $quantite);

        return $this->creerMouvement($type, $produit, $quantite, null, $destination, $lot, $cond, $refType, $refId, $user, $obs);
    }

    /**
     * Sortie (vers $destination si transfert, sinon sortie définitive) en consommant les lots FEFO.
     *
     * @return MouvementStock[] un mouvement par lot consommé
     * @throws OperationImpossibleException
     */
    public function sortir(
        string $type,
        Produit $produit,
        ?Conditionnement $cond,
        float $quantite,
        EmplacementStock $source,
        ?EmplacementStock $destination = null,
        ?string $refType = null,
        ?int $refId = null,
        ?Utilisateur $user = null,
        ?string $obs = null
    ): array {
        $this->verifierQuantite($quantite);

        $lignes = $this->verrouillerLignesDisponibles($source, $produit, $cond);
        $disponible = array_sum(array_map(fn (Stock $s) => (float) $s->getQuantiteDisponible(), $lignes));
        if ($disponible + self::EPSILON < $quantite) {
            throw new OperationImpossibleException(sprintf(
                'Stock insuffisant pour %s%s à « %s » : %s demandé(s), %s disponible(s).',
                $produit->getNom(),
                $cond ? ' (' . $cond->getLibelle() . ')' : '',
                $source->getNom(),
                $this->format($quantite),
                $this->format($disponible)
            ));
        }

        $mouvements = [];
        $reste = $quantite;
        foreach ($lignes as $stock) {
            if ($reste <= self::EPSILON) {
                break;
            }
            $part = min($reste, (float) $stock->getQuantiteDisponible());
            $this->debiter($stock, $part);
            if ($destination) {
                $this->crediter($this->getOrCreateStock($destination, $produit, $stock->getLot(), $stock->getConditionnement(), true), $part);
            }
            $mouvements[] = $this->creerMouvement($type, $produit, $part, $source, $destination, $stock->getLot(), $stock->getConditionnement(), $refType, $refId, $user, $obs);
            $reste = round($reste - $part, 3);
        }

        return $mouvements;
    }

    /**
     * Annule tous les mouvements d'une opération en créant les mouvements inverses (même lot / conditionnement).
     *
     * @return MouvementStock[]
     * @throws OperationImpossibleException si le stock a déjà été consommé à destination
     */
    public function annulerMouvements(string $refType, int $refId, string $typeRetour, ?Utilisateur $user = null, ?string $obs = null): array
    {
        $originaux = $this->em->getRepository(MouvementStock::class)->findBy(['referenceType' => $refType, 'referenceId' => $refId], ['id' => 'ASC']);

        $inverses = [];
        foreach ($originaux as $m) {
            $quantite = (float) $m->getQuantite();
            $source = $m->getEmplacementDestination();
            $destination = $m->getEmplacementSource();

            if ($source) {
                $stock = $this->getOrCreateStock($source, $m->getProduit(), $m->getLot(), $m->getConditionnement(), true);
                if ((float) $stock->getQuantiteDisponible() + self::EPSILON < $quantite) {
                    throw new OperationImpossibleException(sprintf('Annulation impossible : le stock de %s à « %s » a déjà été utilisé.', $m->getProduit()->getNom(), $source->getNom()));
                }
                $this->debiter($stock, $quantite);
            }
            if ($destination) {
                $this->crediter($this->getOrCreateStock($destination, $m->getProduit(), $m->getLot(), $m->getConditionnement(), true), $quantite);
            }

            $inverses[] = $this->creerMouvement($typeRetour, $m->getProduit(), $quantite, $source, $destination, $m->getLot(), $m->getConditionnement(), $refType . '_ANNULATION', $refId, $user, $obs);
        }

        return $inverses;
    }

    /** @return Stock[] lignes disponibles, verrouillées, dans l'ordre FEFO */
    private function verrouillerLignesDisponibles(EmplacementStock $emp, Produit $produit, ?Conditionnement $cond): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->addSelect('CASE WHEN l.dateExpiration IS NULL THEN 1 ELSE 0 END AS HIDDEN sansExpiration')
            ->from(Stock::class, 's')
            ->leftJoin('s.lot', 'l')
            ->where('s.emplacement = :emp AND s.produit = :produit AND s.quantiteDisponible > 0')
            ->setParameter('emp', $emp)
            ->setParameter('produit', $produit)
            ->orderBy('sansExpiration', 'ASC')
            ->addOrderBy('l.dateExpiration', 'ASC')
            ->addOrderBy('s.id', 'ASC');
        $this->filtrerConditionnement($qb, $cond);

        return $qb->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getResult();
    }

    private function getOrCreateStock(EmplacementStock $emp, Produit $produit, ?Lot $lot, ?Conditionnement $cond, bool $verrouiller): Stock
    {
        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Stock::class, 's')
            ->where('s.emplacement = :emp AND s.produit = :produit')
            ->setParameter('emp', $emp)
            ->setParameter('produit', $produit)
            ->setMaxResults(1);
        $lot ? $qb->andWhere('s.lot = :lot')->setParameter('lot', $lot) : $qb->andWhere('s.lot IS NULL');
        $this->filtrerConditionnement($qb, $cond);

        $query = $qb->getQuery();
        if ($verrouiller && $emp->getId()) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }
        $stock = $emp->getId() ? $query->getOneOrNullResult() : null;

        if (!$stock) {
            // Ligne créée dans l'opération courante mais pas encore flushée ?
            foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $e) {
                if ($e instanceof Stock && $e->getEmplacement() === $emp && $e->getProduit() === $produit && $e->getLot() === $lot && $e->getConditionnement() === $cond) {
                    return $e;
                }
            }

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

    private function filtrerConditionnement(QueryBuilder $qb, ?Conditionnement $cond): void
    {
        $cond ? $qb->andWhere('s.conditionnement = :cond')->setParameter('cond', $cond) : $qb->andWhere('s.conditionnement IS NULL');
    }

    private function crediter(Stock $stock, float $quantite): void
    {
        $stock->setQuantiteDisponible(number_format((float) $stock->getQuantiteDisponible() + $quantite, 3, '.', ''));
        $stock->setUpdatedAt(new \DateTimeImmutable());
    }

    private function debiter(Stock $stock, float $quantite): void
    {
        $solde = round((float) $stock->getQuantiteDisponible() - $quantite, 3);
        if ($solde < -self::EPSILON) {
            throw new OperationImpossibleException('Stock insuffisant : le solde deviendrait négatif.');
        }
        $stock->setQuantiteDisponible(number_format(max(0, $solde), 3, '.', ''));
        $stock->setUpdatedAt(new \DateTimeImmutable());
    }

    private function creerMouvement(
        string $type,
        Produit $produit,
        float $quantite,
        ?EmplacementStock $source,
        ?EmplacementStock $destination,
        ?Lot $lot,
        ?Conditionnement $cond,
        ?string $refType,
        ?int $refId,
        ?Utilisateur $user,
        ?string $obs
    ): MouvementStock {
        $mvt = new MouvementStock();
        $mvt->setTypeMouvement($type);
        $mvt->setProduit($produit);
        $mvt->setLot($lot);
        $mvt->setConditionnement($cond);
        $mvt->setEmplacementSource($source);
        $mvt->setEmplacementDestination($destination);
        $mvt->setQuantite(number_format($quantite, 3, '.', ''));
        $mvt->setReferenceType($refType);
        $mvt->setReferenceId($refId);
        $mvt->setUtilisateur($user);
        $mvt->setObservation($obs);
        $this->em->persist($mvt);
        $this->em->flush();

        $this->auditService->log('mouvement_stock', 'CREATE', $mvt->getId(), null, [
            'type' => $type,
            'produit' => $produit->getNom(),
            'lot' => $lot?->getNumeroLot(),
            'quantite' => $quantite,
            'source' => $source?->getNom(),
            'destination' => $destination?->getNom(),
        ]);

        return $mvt;
    }

    private function verifierQuantite(float $quantite): void
    {
        if ($quantite <= 0) {
            throw new OperationImpossibleException('La quantité doit être strictement positive.');
        }
    }

    private function format(float $q): string
    {
        return rtrim(rtrim(number_format($q, 3, ',', ' '), '0'), ',');
    }
}
