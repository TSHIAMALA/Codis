<?php

namespace App\Service;

use App\Entity\Livraison;
use App\Entity\Utilisateur;
use App\Exception\OperationImpossibleException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

class LivraisonService
{
    public const REF_LIVRAISON = 'LIVRAISON';

    public function __construct(
        private EntityManagerInterface $em,
        private StockManagerService $stockService,
        private OrderManagerService $orderService,
        private AuditService $auditService,
        #[Target('livraison')] private WorkflowInterface $livraisonWorkflow,
        #[Target('commande')] private WorkflowInterface $commandeWorkflow,
    ) {}

    /** Paiement préalable exigé ? (Distribution indirecte : distributeur agréé) */
    public function paiementRequis(Livraison $livraison): bool
    {
        $cmd = $livraison->getCommande();

        return $cmd && $cmd->getGrossiste() && $cmd->getGrossiste()->getTypeDistribution() === 'DISTRIBUTEUR_AGREE'
            && $this->orderService->montantPaye($cmd) + 0.005 < (float) $cmd->getMontantTotal();
    }

    /**
     * Expédie le BL : sortie définitive du stock du dépôt, lot par lot (FEFO).
     *
     * @throws OperationImpossibleException
     */
    public function expedier(Livraison $livraison, ?Utilisateur $user): void
    {
        if (!$this->livraisonWorkflow->can($livraison, 'expedier')) {
            throw new OperationImpossibleException('Le BL ' . $livraison->getNumero() . ' ne peut pas être expédié (statut : ' . $livraison->getStatut() . ').');
        }

        $cmd = $livraison->getCommande();
        if ($this->paiementRequis($livraison)) {
            throw new OperationImpossibleException(sprintf(
                'Distribution Indirecte (Distributeur Agréé) : le versement du cash à la caisse est obligatoire AVANT de livrer ! Total payé : %s $ / %s $.',
                number_format($this->orderService->montantPaye($cmd), 2, ',', ' '),
                number_format((float) $cmd->getMontantTotal(), 2, ',', ' ')
            ));
        }

        $emplacement = $this->stockService->getEmplacementDepot($livraison->getDepot());
        if (!$emplacement) {
            throw new OperationImpossibleException('Aucun emplacement de stock actif pour le dépôt ' . ($livraison->getDepot()?->getNom() ?? '(non renseigné)') . '.');
        }

        $this->em->wrapInTransaction(function () use ($livraison, $cmd, $emplacement, $user) {
            foreach ($livraison->getLignes() as $ligne) {
                $quantite = (float) ($ligne->getQuantiteLivree() ?? $ligne->getQuantitePrevue());
                if ($quantite <= 0) {
                    continue;
                }
                $mouvements = $this->stockService->sortir(
                    'VENTE', $ligne->getProduit(), $ligne->getConditionnement(), $quantite, $emplacement, null,
                    self::REF_LIVRAISON, $livraison->getId(), $user, 'Expédition BL ' . $livraison->getNumero()
                );
                // Traçabilité : lot renseigné sur la ligne quand un seul lot a été prélevé
                if (count($mouvements) === 1) {
                    $ligne->setLot($mouvements[0]->getLot());
                }
            }

            $this->livraisonWorkflow->apply($livraison, 'expedier');
            $livraison->setDateDepart(new \DateTime());
            if ($cmd && $this->commandeWorkflow->can($cmd, 'expedier')) {
                $this->commandeWorkflow->apply($cmd, 'expedier');
            }
            $this->em->flush();
            $this->auditService->log('livraison', 'SHIP', $livraison->getId(), null, ['numero' => $livraison->getNumero()]);
        });
    }

    public function confirmerReception(Livraison $livraison): void
    {
        if ($livraison->getStatut() === 'RECEPTION_CONFIRMEE') {
            throw new OperationImpossibleException('La réception du BL ' . $livraison->getNumero() . ' est déjà confirmée.');
        }
        if (!$this->livraisonWorkflow->can($livraison, 'confirmer')) {
            throw new OperationImpossibleException('La réception du BL ' . $livraison->getNumero() . ' ne peut être confirmée : il doit d\'abord être expédié (statut actuel : ' . $livraison->getStatut() . ').');
        }

        $this->em->wrapInTransaction(function () use ($livraison) {
            $this->livraisonWorkflow->apply($livraison, 'confirmer');
            $livraison->setDateLivraison(new \DateTime());
            $cmd = $livraison->getCommande();
            if ($cmd && $this->commandeWorkflow->can($cmd, 'receptionner')) {
                $this->commandeWorkflow->apply($cmd, 'receptionner');
            }
            $this->em->flush();
            $this->auditService->log('livraison', 'RECEIVE', $livraison->getId(), null, ['numero' => $livraison->getNumero()]);
        });
    }

    /**
     * Annule le BL. S'il était en transit, la marchandise est réintégrée dans le dépôt (mêmes lots)
     * et la commande redevient « validée » pour pouvoir générer un nouveau BL.
     */
    public function annuler(Livraison $livraison, ?Utilisateur $user): bool
    {
        if (!$this->livraisonWorkflow->can($livraison, 'annuler')) {
            throw new OperationImpossibleException('Le BL ' . $livraison->getNumero() . ' ne peut plus être annulé (statut : ' . $livraison->getStatut() . ').');
        }

        return $this->em->wrapInTransaction(function () use ($livraison, $user) {
            $reintegres = $this->stockService->annulerMouvements(self::REF_LIVRAISON, $livraison->getId(), 'RETOUR', $user, 'Annulation BL ' . $livraison->getNumero());

            $this->livraisonWorkflow->apply($livraison, 'annuler');
            $cmd = $livraison->getCommande();
            if ($cmd && $this->commandeWorkflow->can($cmd, 'retour_validee')) {
                $this->commandeWorkflow->apply($cmd, 'retour_validee');
            }
            $this->em->flush();
            $this->auditService->log('livraison', 'CANCEL', $livraison->getId(), null, ['numero' => $livraison->getNumero(), 'mouvements_reintegres' => count($reintegres)]);

            return count($reintegres) > 0;
        });
    }
}
