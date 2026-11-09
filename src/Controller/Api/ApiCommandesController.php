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
use App\Service\OrderManagerService;
use App\Service\TarifResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        // Un agent ne voit que ses propres commandes ; les filtres libres sont réservés aux délégués
        if (!$this->isGranted('ROLE_DELEGUE')) {
            $agent = $this->getAgentConnecte($em);
            if (!$agent) {
                return new JsonResponse([]);
            }
            $criteria['agent'] = $agent;
            $delegueId = $grossisteId = null;
        } elseif ($agentId) {
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

    #[IsGranted('ROLE_AGENT_COMMERCIAL', message: 'Création de commande réservée aux agents commerciaux.')]
    #[Route('/commandes/creer', name: 'api_commandes_creer', methods: ['POST'])]
    public function creer(Request $request, EntityManagerInterface $em, OrderManagerService $orderService, TarifResolver $tarifResolver): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $typeCommande = $data['typeCommande'] ?? 'DETAILLANT';
        if (!in_array($typeCommande, ['GROSSISTE', 'DETAILLANT'], true)) {
            return new JsonResponse(['success' => false, 'error' => 'Type de commande invalide'], 422);
        }

        $c = new Commande();
        $c->setNumero($orderService->generateOrderNumber());
        $c->setTypeCommande($typeCommande);
        $c->setCreatedBy($this->getUser());
        $c->setDateCommande(new \DateTime());
        $c->setStatut('SOUMISE');
        $c->setObservation($data['observation'] ?? null);

        if (!empty($data['detailant_id'])) {
            $c->setDetailant($em->getRepository(Detailant::class)->find($data['detailant_id']));
        }
        if (!empty($data['grossiste_id'])) {
            $c->setGrossiste($em->getRepository(Grossiste::class)->find($data['grossiste_id']));
        }
        // L'agent est celui du jeton JWT ; seul un délégué peut saisir pour le compte d'un autre agent
        $agent = $this->getAgentConnecte($em);
        if ($this->isGranted('ROLE_DELEGUE') && !empty($data['agent_id'])) {
            $agent = $em->getRepository(AgentCommercial::class)->find($data['agent_id']);
        }
        $c->setAgent($agent);
        if (!empty($data['delegue_id'])) {
            $c->setDelegue($em->getRepository(Delegue::class)->find($data['delegue_id']));
        }
        if (!empty($data['depot_id'])) {
            $c->setDepot($em->getRepository(Depot::class)->find($data['depot_id']));
        }
        if (!$c->getGrossiste() && !$c->getDetailant()) {
            return new JsonResponse(['success' => false, 'error' => 'Client introuvable'], 422);
        }

        $montantTotal = 0.0;
        $lignesSansTarif = [];
        $lignes = $data['lignes'] ?? [];
        foreach ($lignes as $item) {
            $p = $em->getRepository(Produit::class)->find($item['produit_id'] ?? 0);
            $cond = $em->getRepository(Conditionnement::class)->find($item['conditionnement_id'] ?? 0);
            $qte = (float) ($item['quantite'] ?? 1);
            if (!$p || !$cond || $qte <= 0) continue;

            // Prix imposé par la grille tarifaire : le prixUnitaire envoyé par l'app est ignoré
            $tarif = $tarifResolver->resoudre($p, $cond, $typeCommande, $c->getGrossiste(), $c->getDetailant());
            if (!$tarif || $tarif['prix'] <= 0) {
                $lignesSansTarif[] = $p->getNom() . ' (' . $cond->getLibelle() . ')';
                continue;
            }
            $pu = $tarif['prix'];
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

        if ($lignesSansTarif) {
            return new JsonResponse(['success' => false, 'error' => 'Aucun tarif actif pour : ' . implode(', ', $lignesSansTarif)], 422);
        }
        if ($montantTotal <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'Aucune ligne de commande valide'], 422);
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

    private function getAgentConnecte(EntityManagerInterface $em): ?AgentCommercial
    {
        return $em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $this->getUser(), 'actif' => true]);
    }
}
