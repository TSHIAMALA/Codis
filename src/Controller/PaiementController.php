<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Entity\Grossiste;
use App\Entity\Commande;
use App\Pagination\Page;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CAISSIER', message: 'Les paiements sont réservés à la caisse.')]
#[Route('/paiements')]
class PaiementController extends AbstractController
{
    #[Route('', name: 'app_paiement_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $paiements = Page::depuisRequete($em->createQueryBuilder()
            ->select('p', 'c', 'g', 'd')
            ->from(Paiement::class, 'p')
            ->leftJoin('p.commande', 'c')
            ->leftJoin('c.grossiste', 'g')
            ->leftJoin('c.detailant', 'd')
            ->where('p.statut IN (:statuts)')
            ->setParameter('statuts', ['VALIDE', 'EN_ATTENTE'])
            ->orderBy('p.datePaiement', 'DESC')
            ->addOrderBy('p.id', 'DESC'), $request);

        $grossistes = $em->getRepository(Grossiste::class)->findBy([], ['raisonSociale' => 'ASC']);
        $commandes = $em->createQueryBuilder()
            ->select('c', 'g', 'd')
            ->from(Commande::class, 'c')
            ->leftJoin('c.grossiste', 'g')
            ->leftJoin('c.detailant', 'd')
            ->where('c.statut NOT IN (:exclus)')
            ->setParameter('exclus', ['ANNULEE', 'REJETEE'])
            ->orderBy('c.dateCommande', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        // Soldes par grossiste : deux sommes calculées par la base (commandes engagées, règlements validés)
        $statutsEngages = ['SOUMISE', 'VALIDEE', 'EN_PREPARATION', 'EXPEDIEE', 'LIVREE', 'RECEPTIONNEE'];
        $totauxCommandes = $this->sommeParGrossiste($em->createQueryBuilder()
            ->select('IDENTITY(c.grossiste) AS grossiste', 'SUM(c.montantTotal) AS total')
            ->from(Commande::class, 'c')
            ->where('c.grossiste IS NOT NULL AND c.statut IN (:statuts)')
            ->setParameter('statuts', $statutsEngages)
            ->groupBy('c.grossiste'));
        $totauxPaiements = $this->sommeParGrossiste($em->createQueryBuilder()
            ->select('IDENTITY(c.grossiste) AS grossiste', 'SUM(p.montant) AS total')
            ->from(Paiement::class, 'p')
            ->join('p.commande', 'c')
            ->where("c.grossiste IS NOT NULL AND c.statut IN (:statuts) AND p.statut = 'VALIDE'")
            ->setParameter('statuts', $statutsEngages)
            ->groupBy('c.grossiste'));

        $soldesGrossistes = [];
        foreach ($grossistes as $g) {
            $totalCommandes = $totauxCommandes[$g->getId()] ?? 0.0;
            $totalPaiements = $totauxPaiements[$g->getId()] ?? 0.0;

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

    /** @return array<int, float> id du grossiste => total */
    private function sommeParGrossiste(QueryBuilder $qb): array
    {
        $totaux = [];
        foreach ($qb->getQuery()->getArrayResult() as $ligne) {
            $totaux[(int) $ligne['grossiste']] = (float) $ligne['total'];
        }

        return $totaux;
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

        if (in_array($commande->getStatut(), ['ANNULEE', 'REJETEE'], true)) {
            $this->addFlash('danger', 'Impossible d\'encaisser un règlement sur la commande ' . $commande->getNumero() . ' : elle est ' . strtolower($commande->getStatut()) . '.');
            return $this->redirectToRoute('app_paiement_index');
        }

        $montant = round((float) $request->request->get('montant', '0'), 2);
        if ($montant <= 0) {
            $this->addFlash('danger', 'Le montant du règlement doit être supérieur à zéro.');
            return $this->redirectToRoute('app_paiement_index');
        }

        $p = new Paiement();
        $p->setCommande($commande);
        $p->setMontant(number_format($montant, 2, '.', ''));
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
        $montant = round((float) $request->request->get('montant', $p->getMontant()), 2);
        if ($montant <= 0) {
            $this->addFlash('danger', 'Le montant du règlement doit être supérieur à zéro.');
            return $this->redirectToRoute('app_paiement_index');
        }
        $p->setMontant(number_format($montant, 2, '.', ''));
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