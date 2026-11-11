<?php

namespace App\Service;

use App\Entity\Conditionnement;
use App\Entity\EmplacementStock;
use App\Entity\MouvementStock;
use App\Entity\Produit;
use App\Entity\TransfertStock;
use App\Entity\TransfertStockLigne;
use App\Entity\Utilisateur;
use App\Exception\OperationImpossibleException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Transfert entre sites (usine → dépôt, dépôt → dépôt) en deux temps :
 *  - expédition : le stock quitte le site de départ, lot par lot (FEFO), et passe « en transit » ;
 *  - réception  : le dépôt d'arrivée reçoit exactement les lots expédiés.
 * Annulé pendant le transit, le stock retourne au site de départ.
 */
class TransfertService
{
    public const REF = 'TRANSFERT_STOCK';

    public function __construct(
        private EntityManagerInterface $em,
        private StockManagerService $stockService,
        private AuditService $auditService,
        #[Target('transfert')] private WorkflowInterface $transfertWorkflow,
    ) {}

    /** @throws OperationImpossibleException */
    public function expedier(EmplacementStock $source, EmplacementStock $destination, Produit $produit, Conditionnement $cond, float $quantite, ?Utilisateur $user, ?string $observation): TransfertStock
    {
        if ($source === $destination) {
            throw new OperationImpossibleException('Le site de départ et le site d\'arrivée doivent être différents.');
        }
        if (!in_array($source->getTypeEmplacement(), ['USINE', 'DEPOT'], true) || $destination->getTypeEmplacement() !== 'DEPOT') {
            throw new OperationImpossibleException('Un transfert part d\'une usine ou d\'un dépôt et arrive dans un dépôt.');
        }
        if ($cond->getProduit() !== $produit) {
            throw new OperationImpossibleException('Le conditionnement choisi ne correspond pas au produit.');
        }

        return $this->em->wrapInTransaction(function () use ($source, $destination, $produit, $cond, $quantite, $user, $observation) {
            $t = new TransfertStock();
            $t->setNumero('TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5)));
            $t->setEmplacementSource($source);
            $t->setEmplacementDestination($destination);
            $t->setDateDemande(new \DateTime());
            $t->setDateExpedition(new \DateTime());
            $t->setStatut('EN_TRANSIT');
            $t->setUtilisateur($user);
            $t->setObservation($observation ?: null);
            $this->em->persist($t);
            $this->em->flush();

            // Sortie du site de départ ; une ligne de transfert par lot prélevé
            $mouvements = $this->stockService->sortir('TRANSFERT', $produit, $cond, $quantite, $source, null,
                self::REF, $t->getId(), $user, 'Expédition transfert ' . $t->getNumero() . ' vers ' . $destination->getNom());
            foreach ($mouvements as $m) {
                $ligne = new TransfertStockLigne();
                $ligne->setTransfert($t);
                $ligne->setProduit($produit);
                $ligne->setLot($m->getLot());
                $ligne->setConditionnement($cond);
                $ligne->setQuantiteEnvoyee($m->getQuantite());
                $t->getLignes()->add($ligne);
                $this->em->persist($ligne);
            }
            $this->em->flush();
            $this->auditService->log('transfert_stock', 'SHIP', $t->getId(), null, [
                'numero' => $t->getNumero(), 'produit' => $produit->getNom(), 'quantite' => $quantite,
                'source' => $source->getNom(), 'destination' => $destination->getNom(),
            ]);

            return $t;
        });
    }

    /** @throws OperationImpossibleException */
    public function receptionner(TransfertStock $t, ?Utilisateur $user): void
    {
        if (!$this->transfertWorkflow->can($t, 'receptionner')) {
            throw new OperationImpossibleException('Le transfert ' . $t->getNumero() . ' n\'est pas en transit (statut : ' . $t->getStatut() . ').');
        }

        // On ne réceptionne que ce qui a réellement quitté le site de départ
        $sorties = $this->em->getRepository(MouvementStock::class)->findBy(['referenceType' => self::REF, 'referenceId' => $t->getId()]);
        if (!$sorties) {
            throw new OperationImpossibleException('Le transfert ' . $t->getNumero() . ' est antérieur au suivi des stocks : aucune sortie n\'a été enregistrée au départ. Annulez-le, puis refaites le transfert.');
        }

        $this->em->wrapInTransaction(function () use ($t, $user, $sorties) {
            foreach ($sorties as $m) {
                $this->stockService->entrer('TRANSFERT', $m->getProduit(), (float) $m->getQuantite(), $t->getEmplacementDestination(),
                    $m->getLot(), $m->getConditionnement(), self::REF . '_RECEPTION', $t->getId(), $user, 'Réception transfert ' . $t->getNumero());
            }
            foreach ($t->getLignes() as $ligne) {
                $ligne->setQuantiteRecue($ligne->getQuantiteEnvoyee());
            }
            $this->transfertWorkflow->apply($t, 'receptionner');
            $t->setDateReception(new \DateTime());
            $this->em->flush();
            $this->auditService->log('transfert_stock', 'RECEIVE', $t->getId(), null, ['numero' => $t->getNumero()]);
        });
    }

    /** @throws OperationImpossibleException */
    public function annuler(TransfertStock $t, ?Utilisateur $user): void
    {
        if (!$this->transfertWorkflow->can($t, 'annuler')) {
            throw new OperationImpossibleException('Le transfert ' . $t->getNumero() . ' ne peut plus être annulé (statut : ' . $t->getStatut() . ').');
        }

        $this->em->wrapInTransaction(function () use ($t, $user) {
            $this->stockService->annulerMouvements(self::REF, $t->getId(), 'RETOUR', $user, 'Annulation transfert ' . $t->getNumero());
            $this->transfertWorkflow->apply($t, 'annuler');
            $this->em->flush();
            $this->auditService->log('transfert_stock', 'CANCEL', $t->getId(), null, ['numero' => $t->getNumero()]);
        });
    }
}
