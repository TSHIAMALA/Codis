<?php

namespace App\Controller\Api;

use App\Entity\Detailant;
use App\Entity\PortefeuilleClient;
use App\Entity\Grossiste;
use App\Entity\AgentCommercial;
use App\Entity\ZoneCommerciale;
use App\Entity\Ville;
use App\Entity\Region;
use App\Entity\Pays;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiClientsController extends AbstractController
{
    #[Route('/clients/portefeuille', name: 'api_clients_portefeuille', methods: ['GET'])]
    public function getPortefeuille(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $agentId = $request->query->get('agent_id');
        $agent = $agentId ? $em->getRepository(AgentCommercial::class)->find($agentId) : null;

        if ($agent) {
            $liens = $em->getRepository(PortefeuilleClient::class)->findBy(['agent' => $agent, 'actif' => true]);
            $detailants = array_map(fn($l) => $l->getDetailant(), $liens);
        } else {
            $detailants = $em->getRepository(Detailant::class)->findBy(['actif' => true], ['nom' => 'ASC'], 50);
        }

        $res = [];
        foreach ($detailants as $d) {
            if (!$d) continue;
            $res[] = [
                'id' => $d->getId(),
                'code' => $d->getCode(),
                'nom' => $d->getNom(),
                'nomResponsable' => $d->getNomResponsable(),
                'telephone' => $d->getTelephone(),
                'email' => $d->getEmail(),
                'adresse' => $d->getAdresse(),
                'statut' => $d->getStatut(),
                'ville' => $d->getVille() ? $d->getVille()->getNom() : '',
                'zone' => $d->getZone() ? $d->getZone()->getNom() : '',
            ];
        }

        return new JsonResponse($res);
    }

    #[Route('/clients/nouveau', name: 'api_clients_create', methods: ['POST'])]
    public function createClient(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $d = new Detailant();
        $d->setCode('DET-' . strtoupper(substr(uniqid(), -5)));
        $d->setNom($data['nom'] ?? 'Nouveau point de vente');
        $d->setNomResponsable($data['nomResponsable'] ?? '');
        $d->setTelephone($data['telephone'] ?? '');
        $d->setEmail($data['email'] ?? null);
        $d->setAdresse($data['adresse'] ?? '');
        $d->setStatut('ACTIF');

        if (!empty($data['pays_id'])) {
            $d->setPays($em->getRepository(Pays::class)->find($data['pays_id']));
        }
        if (!empty($data['region_id'])) {
            $d->setRegion($em->getRepository(Region::class)->find($data['region_id']));
        }
        if (!empty($data['ville_id'])) {
            $d->setVille($em->getRepository(Ville::class)->find($data['ville_id']));
        }
        if (!empty($data['zone_id'])) {
            $d->setZone($em->getRepository(ZoneCommerciale::class)->find($data['zone_id']));
        }

        $em->persist($d);

        // Attach to agent if provided
        if (!empty($data['agent_id'])) {
            $agent = $em->getRepository(AgentCommercial::class)->find($data['agent_id']);
            if ($agent) {
                $pf = new PortefeuilleClient();
                $pf->setAgent($agent);
                $pf->setDetailant($d);
                $em->persist($pf);
            }
        }

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'client' => [
                'id' => $d->getId(),
                'code' => $d->getCode(),
                'nom' => $d->getNom(),
            ]
        ], 201);
    }

    #[Route('/grossistes', name: 'api_grossistes_list', methods: ['GET'])]
    public function listGrossistes(EntityManagerInterface $em): JsonResponse
    {
        $grossistes = $em->getRepository(Grossiste::class)->findBy(['statut' => 'ACTIF']);
        $res = [];
        foreach ($grossistes as $g) {
            $res[] = [
                'id' => $g->getId(),
                'code' => $g->getCode(),
                'raisonSociale' => $g->getRaisonSociale(),
                'nomResponsable' => $g->getNomResponsable(),
                'telephone' => $g->getTelephone(),
                'limiteCredit' => (float) $g->getLimiteCredit(),
                'statut' => $g->getStatut(),
            ];
        }
        return new JsonResponse($res);
    }
}