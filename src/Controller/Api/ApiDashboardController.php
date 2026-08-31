<?php

namespace App\Controller\Api;

use App\Entity\Commande;
use App\Entity\Detailant;
use App\Entity\Livraison;
use App\Entity\Paiement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiDashboardController extends AbstractController
{
    #[Route('/dashboard/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function stats(EntityManagerInterface $em): JsonResponse
    {
        $totalClients = $em->getRepository(Detailant::class)->count(['actif' => true]);
        $totalCommandes = $em->getRepository(Commande::class)->count([]);
        $livraisonsEnCours = $em->getRepository(Livraison::class)->count(['statut' => ['EXPEDIEE', 'EN_TRANSIT', 'PREPARATION']]);

        $commandesRecentes = $em->getRepository(Commande::class)->findBy([], ['dateCommande' => 'DESC'], 5);
        $recent = [];
        foreach ($commandesRecentes as $c) {
            $recent[] = [
                'id' => $c->getId(),
                'numero' => $c->getNumero(),
                'clientNom' => $c->getClientNom(),
                'montant' => (float) $c->getMontantTotal(),
                'statut' => $c->getStatut(),
                'date' => $c->getDateCommande() ? $c->getDateCommande()->format('d/m/Y') : '',
            ];
        }

        return new JsonResponse([
            'totalClients' => $totalClients,
            'totalCommandes' => $totalCommandes,
            'livraisonsEnCours' => $livraisonsEnCours,
            'commandesRecentes' => $recent,
        ]);
    }
}