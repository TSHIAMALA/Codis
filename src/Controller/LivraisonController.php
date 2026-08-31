<?php

namespace App\Controller;

use App\Entity\Livraison;
use App\Entity\Paiement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/livraisons')]
class LivraisonController extends AbstractController
{
    #[Route('', name: 'app_livraison_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $livraisons = $em->getRepository(Livraison::class)->findBy([], ['id' => 'DESC']);

        return $this->render('livraisons/index.html.twig', [
            'livraisons' => $livraisons,
        ]);
    }

    #[Route('/{id}/expedier', name: 'app_livraison_expedier', methods: ['POST'])]
    public function expedier(Livraison $livraison, EntityManagerInterface $em): Response
    {
        $cmd = $livraison->getCommande();

        // Règle Distribution Indirecte (Distributeur Agréé) : Versement Cash préalable obligatoire à la caisse
        if ($cmd && $cmd->getGrossiste() && $cmd->getGrossiste()->getTypeDistribution() === 'DISTRIBUTEUR_AGREE') {
            $paiements = $em->getRepository(Paiement::class)->findBy(['commande' => $cmd, 'statut' => 'VALIDE']);
            $totalPaye = 0.0;
            foreach ($paiements as $p) {
                $totalPaye += (float) $p->getMontant();
            }

            if ($totalPaye < (float)$cmd->getMontantTotal()) {
                $this->addFlash('danger', 'Distribution Indirecte (Distributeur Agréé) : Le versement du cash à la caisse est obligatoire AVANT de livrer ! Total payé : ' . number_format($totalPaye, 2, ',', ' ') . ' $ / ' . number_format((float)$cmd->getMontantTotal(), 2, ',', ' ') . ' $.');
                return $this->redirectToRoute('app_livraison_index');
            }
        }

        $livraison->setStatut('EN_TRANSIT');
        $livraison->setDateDepart(new \DateTime());
        if ($cmd) {
            $cmd->setStatut('EXPEDIEE');
        }
        $em->flush();

        $this->addFlash('info', 'Livraison ' . $livraison->getNumero() . ' expédiée (En transit).');
        return $this->redirectToRoute('app_livraison_index');
    }

    #[Route('/{id}/confirmer', name: 'app_livraison_confirmer', methods: ['POST'])]
    public function confirmer(Livraison $livraison, EntityManagerInterface $em): Response
    {
        $livraison->setStatut('RECEPTION_CONFIRMEE');
        $livraison->setDateLivraison(new \DateTime());
        if ($livraison->getCommande()) {
            $livraison->getCommande()->setStatut('RECEPTIONNEE');
        }
        $em->flush();

        $this->addFlash('success', 'Réception du Bon de Livraison ' . $livraison->getNumero() . ' confirmée avec succès !');
        return $this->redirectToRoute('app_livraison_index');
    }

    #[Route('/{id}/modifier', name: 'app_livraison_modifier', methods: ['POST'])]
    public function modifier(Livraison $livraison, Request $request, EntityManagerInterface $em): Response
    {
        $livraison->setObservation($request->request->get('observation', $livraison->getObservation()));
        $datePrevueStr = $request->request->get('datePrevue');
        if (!empty($datePrevueStr)) {
            $livraison->setDatePrevue(new \DateTime($datePrevueStr));
        }

        $em->flush();
        $this->addFlash('info', 'Bon de Livraison ' . $livraison->getNumero() . ' mis à jour.');
        return $this->redirectToRoute('app_livraison_index');
    }

    #[Route('/{id}/annuler', name: 'app_livraison_annuler', methods: ['POST'])]
    public function annuler(Livraison $livraison, EntityManagerInterface $em): Response
    {
        if ($livraison->getStatut() !== 'RECEPTION_CONFIRMEE') {
            $livraison->setStatut('ANNULEE');
            $em->flush();
            $this->addFlash('warning', 'Livraison ' . $livraison->getNumero() . ' annulée (suppression logique).');
        }
        return $this->redirectToRoute('app_livraison_index');
    }
}