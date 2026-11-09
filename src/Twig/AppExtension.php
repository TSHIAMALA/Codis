<?php

namespace App\Twig;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private EntityManagerInterface $em) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('demandes_acces_count', $this->countDemandesAcces(...)),
        ];
    }

    /** Nombre de demandes d'accès en attente (comptes inactifs sans rôle), affiché dans le menu admin. */
    public function countDemandesAcces(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(Utilisateur::class, 'u')
            ->leftJoin('u.rolesEntities', 'r')
            ->where('u.actif = false')
            ->andWhere('r.id IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
