<?php

namespace App\Pagination;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Une page de résultats d'une requête Doctrine, affichée avec templates/_pagination.html.twig.
 * Itérable : {% for commande in commandes %} fonctionne comme avec un tableau.
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
final class Page implements \IteratorAggregate, \Countable
{
    public const PAR_PAGE = 25;

    /** @param T[] $elements */
    private function __construct(
        private array $elements,
        public readonly int $numero,
        public readonly int $parPage,
        public readonly int $total,
    ) {}

    /**
     * @param bool $jointureCollection true si la requête charge une collection (OneToMany) par jointure :
     *                                 le paginateur compte alors les entités racines, pas les lignes SQL.
     */
    public static function depuisRequete(QueryBuilder $qb, Request $request, int $parPage = self::PAR_PAGE, bool $jointureCollection = false): self
    {
        $numero = max(1, $request->query->getInt('page', 1));
        $qb->setFirstResult(($numero - 1) * $parPage)->setMaxResults($parPage);
        $paginateur = new Paginator($qb, $jointureCollection);
        $total = count($paginateur);

        // Page demandée au-delà de la dernière (ex. après une suppression) : on affiche la dernière
        $derniere = max(1, (int) ceil($total / $parPage));
        if ($numero > $derniere) {
            $numero = $derniere;
            $qb->setFirstResult(($numero - 1) * $parPage);
            $paginateur = new Paginator($qb, $jointureCollection);
        }

        return new self(iterator_to_array($paginateur->getIterator(), false), $numero, $parPage, $total);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->elements);
    }

    /** Nombre d'éléments sur la page courante (le total est dans $total). */
    public function count(): int
    {
        return count($this->elements);
    }

    public function getPages(): int
    {
        return max(1, (int) ceil($this->total / $this->parPage));
    }

    public function getPremier(): int
    {
        return $this->total === 0 ? 0 : ($this->numero - 1) * $this->parPage + 1;
    }

    public function getDernier(): int
    {
        return min($this->total, $this->numero * $this->parPage);
    }

    /** Numéros de pages à afficher autour de la page courante (null = ellipse). @return array<int|null> */
    public function getFenetre(int $rayon = 2): array
    {
        $pages = [];
        $precedent = 0;
        for ($p = 1; $p <= $this->getPages(); $p++) {
            if ($p === 1 || $p === $this->getPages() || abs($p - $this->numero) <= $rayon) {
                if ($p - $precedent > 1) {
                    $pages[] = null;
                }
                $pages[] = $p;
                $precedent = $p;
            }
        }

        return $pages;
    }
}
