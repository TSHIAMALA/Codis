<?php

namespace App\Controller\Api;

use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Produit;
use App\Entity\Conditionnement;
use App\Entity\Grossiste;
use App\Entity\Detailant;
use App\Entity\AgentCommercial;
use App\Entity\Delegue;
use App\Entity\Depot;
use App\Entity\Utilisateur;
use App\Service\OrderManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiCommandesController extends AbstractController
{
    #[Route('/commandes', name: 'api_commandes_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $agentId = $request->query->get('agent_id');
        $delegueId = $request->query->get('delegue_id');
        $grossisteId = $request->query->get('grossiste_id');

        $criteria = [];
        if ($agentId) {
            $agent = $em->getRepository(AgentCommercial::class)->find($agentId);
            if ($agent) $criteria['agent'] = $agent;
        }
        if ($delegueId) {
            $delegue = $em->getRepository(Delegue::class)->find($delegueId);
            if ($delegue) $criteria['delegue'] = $delegue;
        }
        if ($grossisteId) {
            $grossiste = $em->getRepository(Grossiste::class)->find($grossisteId);
            if ($grossiste) $criteria['grossiste'] = $grossiste;
        }

        $commandes = $em->getRepository(Commande::class)->findBy($criteria, ['dateCommande' => 'DESC'], 50);

        $res = [];
        foreach ($commandes as $c) {
            $lignes = [];
            foreach ($c->getLignes() as $l) {
                $lignes[] = [
                    'id' => $l->getId(),
                    'produit' => $l->getProduit() ? $l->getProduit()->getNom() : '',
                    'conditionnement' => $l->getConditionnement() ? $l->getConditionnement()->getLibelle() : '',
                    'quantite' => (float) $l->getQuantite(),
                    'prixUnitaire' => (float) $l->getPrixUnitaire(),
                    'montant' => (float) $l->getMontant(),
                ];
            }

            $res[] = [
                'id' => $c->getId(),
                'numero' => $c->getNumero(),
                'typeCommande' => $c->getTypeCommande(),
                'clientNom' => $c->getClientNom(),
                'dateCommande' => $c->getDateCommande() ? $c->getDateCommande()->format('Y-m-d H:i') : '',
                'statut' => $c->getStatut(),
                'montantTotal' => (float) $c->getMontantTotal(),
                'depot' => $c->getDepot() ? $c->getDepot()->getNom() : '',
                'lignes' => $lignes,
            ];
        }

        return new JsonResponse($res);
    }

    #[Route('/commandes/creer', name: 'api_commandes_creer', methods: ['POST'])]
    public function creer(Request $request, EntityManagerInterface $em, OrderManagerService $orderService): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $c = new Commande();
        $c->setNumero($orderService->generateOrderNumber());
        $c->setTypeCommande($data['typeCommande'] ?? 'DETAILLANT');
        $c->setDateCommande(new \DateTime());
        $c->setStatut('SOUMISE');
        $c->setObservation($data['observation'] ?? null);

        if (!empty($data['detailant_id'])) {
            $c->setDetailant($em->getRepository(Detailant::class)->find($data['detailant_id']));
        }
        if (!empty($data['grossiste_id'])) {
            $c->setGrossiste($em->getRepository(Grossiste::class)->find($data['grossiste_id']));
        }
        if (!empty($data['agent_id'])) {
            $c->setAgent($em->getRepository(AgentCommercial::class)->find($data['agent_id']));
        }
        if (!empty($data['delegue_id'])) {
            $c->setDelegue($em->getRepository(Delegue::class)->find($data['delegue_id']));
        }
        if (!empty($data['depot_id'])) {
            $c->setDepot($em->getRepository(Depot::class)->find($data['depot_id']));
        }
        if (!empty($data['user_id'])) {
            $c->setCreatedBy($em->getRepository(Utilisateur::class)->find($data['user_id']));
        }

        $montantTotal = 0.0;
        $lignes = $data['lignes'] ?? [];
        foreach ($lignes as $item) {
            $p = $em->getRepository(Produit::class)->find($item['produit_id']);
            $cond = $em->getRepository(Conditionnement::class)->find($item['conditionnement_id']);
            if (!$p || !$cond) continue;

            $qte = (float) ($item['quantite'] ?? 1);
            $pu = (float) ($item['prixUnitaire'] ?? 0);
            $ligneMontant = $qte * $pu;
            $montantTotal += $ligneMontant;

            $cl = new CommandeLigne();
            $cl->setCommande($c);
            $cl->setProduit($p);
            $cl->setConditionnement($cond);
            $cl->setQuantite((string) $qte);
            $cl->setPrixUnitaire((string) $pu);
            $cl->setMontant((string) $ligneMontant);
            $c->getLignes()->add($cl);
            $em->persist($cl);
        }

        $c->setMontantTotal((string) $montantTotal);
        $em->persist($c);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $c->getId(),
            'numero' => $c->getNumero(),
            'montantTotal' => $montantTotal,
            'statut' => $c->getStatut(),
        ], 201);
    }
}