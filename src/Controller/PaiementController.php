<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Entity\Grossiste;
use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/paiements')]
class PaiementController extends AbstractController
{
    #[Route('', name: 'app_paiement_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $paiements = $em->getRepository(Paiement::class)->findBy(['statut' => ['VALIDE', 'EN_ATTENTE']], ['datePaiement' => 'DESC']);
        $grossistes = $em->getRepository(Grossiste::class)->findAll();
        $commandes = $em->getRepository(Commande::class)->createQueryBuilder('c')
            ->where('c.statut NOT IN (:exclus)')
            ->setParameter('exclus', ['ANNULEE', 'REJETEE'])
            ->orderBy('c.dateCommande', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        // Calcul des Avoirs / Soldes Créditeurs par Grossiste
        $soldesGrossistes = [];
        foreach ($grossistes as $g) {
            $cmds = $em->getRepository(Commande::class)->findBy([
                'grossiste' => $g,
                'statut' => ['SOUMISE', 'VALIDEE', 'EN_PREPARATION', 'EXPEDIEE', 'LIVREE', 'RECEPTIONNEE']
            ]);

            $totalCommandes = 0.0;
            $totalPaiements = 0.0;

            foreach ($cmds as $c) {
                $totalCommandes += (float) $c->getMontantTotal();
                foreach ($c->getPaiements() as $p) {
                    if ($p->getStatut() === 'VALIDE') {
                        $totalPaiements += (float) $p->getMontant();
                    }
                }
            }

            $solde = $totalPaiements - $totalCommandes; // Si > 0, c'est un avoir / avance
            $soldesGrossistes[] = [
                'grossiste' => $g,
                'totalCommandes' => $totalCommandes,
                'totalPaiements' => $totalPaiements,
                'soldeCrediteur' => max(0, $solde),
                'resteDu' => max(0, -$solde),
            ];
        }

        return $this->render('paiements/index.html.twig', [
            'paiements' => $paiements,
            'grossistes' => $grossistes,
            'commandes' => $commandes,
            'soldesGrossistes' => $soldesGrossistes,
        ]);
    }

    #[Route('/nouveau', name: 'app_paiement_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        $commandeId = $request->request->get('commande_id');
        $commande = $commandeId ? $em->getRepository(Commande::class)->find($commandeId) : null;

        if (!$commande) {
            $this->addFlash('danger', 'Veuillez sélectionner une commande valide.');
            return $this->redirectToRoute('app_paiement_index');
        }

        $p = new Paiement();
        $p->setCommande($commande);
        $p->setMontant($request->request->get('montant', '0.00'));
        $p->setModePaiement($request->request->get('modePaiement', 'ESPECES'));
        $p->setReference($request->request->get('reference'));
        $p->setObservation($request->request->get('observation'));
        $p->setStatut('VALIDE');
        $p->setDatePaiement(new \DateTime());
        $p->setUtilisateur($this->getUser());

        $em->persist($p);
        $em->flush();

        $this->addFlash('success', 'Règlement de ' . number_format((float)$p->getMontant(), 2, ',', ' ') . ' $ enregistré avec succès pour la commande ' . $commande->getNumero() . ' !');
        return $this->redirectToRoute('app_paiement_index');
    }

    #[Route('/{id}/modifier', name: 'app_paiement_modifier', methods: ['POST'])]
    public function modifier(Paiement $p, Request $request, EntityManagerInterface $em): Response
    {
        $p->setMontant($request->request->get('montant', $p->getMontant()));
        $p->setModePaiement($request->request->get('modePaiement', $p->getModePaiement()));
        $p->setReference($request->request->get('reference', $p->getReference()));
        $p->setObservation($request->request->get('observation', $p->getObservation()));

        $em->flush();
        $this->addFlash('info', 'Paiement de la commande ' . ($p->getCommande() ? $p->getCommande()->getNumero() : '') . ' mis à jour.');
        return $this->redirectToRoute('app_paiement_index');
    }

    #[Route('/{id}/supprimer', name: 'app_paiement_supprimer', methods: ['POST'])]
    public function supprimer(Paiement $p, EntityManagerInterface $em): Response
    {
        // Soft delete: update statut to REJETE
        $p->setStatut('REJETE');
        $em->flush();

        $this->addFlash('warning', 'Paiement rejeté / désactivé (suppression logique).');
        return $this->redirectToRoute('app_paiement_index');
    }
}