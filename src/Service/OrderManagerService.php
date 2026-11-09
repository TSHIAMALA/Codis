<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\Livraison;
use App\Entity\LivraisonLigne;
use App\Entity\Grossiste;
use App\Entity\Utilisateur;
use App\Exception\OperationImpossibleException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

class OrderManagerService
{
    /** Statuts d'une commande engagée (comptée dans l'encours client). */
    private const STATUTS_ENGAGES = ['VALIDEE', 'EN_PREPARATION', 'EXPEDIEE', 'LIVREE', 'RECEPTIONNEE'];

    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $auditService,
        #[Target('commande')] private WorkflowInterface $commandeWorkflow,
        #[Target('livraison')] private WorkflowInterface $livraisonWorkflow,
    ) {}

    public function generateOrderNumber(): string
    {
        return 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function generateDeliveryNumber(): string
    {
        return 'BL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function montantPaye(Commande $cmd): float
    {
        $paye = 0.0;
        foreach ($cmd->getPaiements() as $p) {
            if ($p->getStatut() === 'VALIDE') {
                $paye += (float) $p->getMontant();
            }
        }

        return round($paye, 2);
    }

    public function verifierLimiteCredit(Grossiste $grossiste, float $nouveauMontant, ?Commande $exclure = null): array
    {
        $limite = (float) $grossiste->getLimiteCredit();
        $commandes = $this->em->getRepository(Commande::class)->findBy([
            'grossiste' => $grossiste,
            'statut' => self::STATUTS_ENGAGES,
        ]);

        $encours = 0.0;
        foreach ($commandes as $cmd) {
            if ($cmd === $exclure) {
                continue;
            }
            $encours += max(0, (float) $cmd->getMontantTotal() - $this->montantPaye($cmd));
        }

        $autorise = $limite <= 0 || ($encours + $nouveauMontant) <= $limite;
        return [
            'autorise' => $autorise,
            'limite' => $limite,
            'encours' => round($encours, 2),
            'disponible' => max(0, round($limite - $encours, 2)),
        ];
    }

    /**
     * Valide la commande et génère son bon de livraison.
     * Les distributeurs agréés paient avant livraison : la limite de crédit ne s'applique qu'aux autres grossistes.
     *
     * @throws OperationImpossibleException
     */
    public function validerCommande(Commande $cmd, ?Utilisateur $valideur = null): Livraison
    {
        if (!$this->commandeWorkflow->can($cmd, 'valider')) {
            throw new OperationImpossibleException('La commande ' . $cmd->getNumero() . ' ne peut plus être validée (statut : ' . $cmd->getStatut() . ').');
        }

        $grossiste = $cmd->getGrossiste();
        if ($grossiste && $grossiste->getTypeDistribution() !== 'DISTRIBUTEUR_AGREE') {
            $credit = $this->verifierLimiteCredit($grossiste, (float) $cmd->getMontantTotal() - $this->montantPaye($cmd), $cmd);
            if (!$credit['autorise']) {
                throw new OperationImpossibleException(sprintf(
                    'Limite de crédit dépassée pour %s : encours %s $ + commande %s $ > limite %s $ (disponible : %s $). Encaissez un règlement ou ajustez la limite.',
                    $grossiste->getRaisonSociale(),
                    number_format($credit['encours'], 2, ',', ' '),
                    number_format((float) $cmd->getMontantTotal(), 2, ',', ' '),
                    number_format($credit['limite'], 2, ',', ' '),
                    number_format($credit['disponible'], 2, ',', ' ')
                ));
            }
        }

        return $this->em->wrapInTransaction(function () use ($cmd, $valideur) {
            $this->commandeWorkflow->apply($cmd, 'valider');
            $liv = $this->creerLivraison($cmd);

            $this->em->flush();
            $this->auditService->log('commande', 'VALIDATE', $cmd->getId(), null, [
                'numero' => $cmd->getNumero(),
                'bon_livraison' => $liv->getNumero(),
                'valide_par' => $valideur ? $valideur->getUsername() : 'System',
            ]);

            return $liv;
        });
    }

    /** Génère un nouveau BL pour une commande validée dont le précédent a été annulé. */
    public function regenererLivraison(Commande $cmd): Livraison
    {
        if ($cmd->getStatut() !== 'VALIDEE' || $this->getLivraisonActive($cmd)) {
            throw new OperationImpossibleException('Un nouveau bon de livraison ne peut être généré que pour une commande validée sans BL actif.');
        }

        return $this->em->wrapInTransaction(function () use ($cmd) {
            $liv = $this->creerLivraison($cmd);
            $this->em->flush();
            $this->auditService->log('livraison', 'CREATE', $liv->getId(), null, ['numero' => $liv->getNumero(), 'commande' => $cmd->getNumero()]);

            return $liv;
        });
    }

    /**
     * Annule la commande et ses BL non expédiés. Une commande dont le BL est en transit doit
     * d'abord voir ce BL annulé (le stock est alors réintégré).
     */
    public function annulerCommande(Commande $cmd): void
    {
        if (!$this->commandeWorkflow->can($cmd, 'annuler')) {
            $raison = $cmd->getStatut() === 'EXPEDIEE'
                ? 'sa livraison est en transit : annulez d\'abord le bon de livraison pour réintégrer le stock.'
                : 'statut ' . $cmd->getStatut() . '.';
            throw new OperationImpossibleException('Impossible d\'annuler la commande ' . $cmd->getNumero() . ' : ' . $raison);
        }

        if ($this->montantPaye($cmd) > 0) {
            throw new OperationImpossibleException('Impossible d\'annuler la commande ' . $cmd->getNumero() . ' : des règlements ont été encaissés. Rejetez-les d\'abord dans Paiements.');
        }

        $this->em->wrapInTransaction(function () use ($cmd) {
            foreach ($cmd->getLivraisons() as $liv) {
                if (in_array($liv->getStatut(), ['PLANIFIEE', 'PREPARATION'], true)) {
                    $this->livraisonWorkflow->apply($liv, 'annuler');
                }
            }
            $this->commandeWorkflow->apply($cmd, 'annuler');
            $this->em->flush();
            $this->auditService->log('commande', 'CANCEL', $cmd->getId(), null, ['numero' => $cmd->getNumero()]);
        });
    }

    public function getLivraisonActive(Commande $cmd): ?Livraison
    {
        foreach ($cmd->getLivraisons() as $liv) {
            if ($liv->getStatut() !== 'ANNULEE') {
                return $liv;
            }
        }

        return null;
    }

    private function creerLivraison(Commande $cmd): Livraison
    {
        $liv = new Livraison();
        $liv->setNumero($this->generateDeliveryNumber());
        $liv->setCommande($cmd);
        $liv->setDepot($cmd->getDepot());
        $liv->setStatut('PREPARATION');
        $liv->setDatePrevue(new \DateTime('+1 day'));
        $liv->setObservation('Génération automatique après validation de la commande ' . $cmd->getNumero());
        $cmd->getLivraisons()->add($liv);
        $this->em->persist($liv);

        foreach ($cmd->getLignes() as $ligne) {
            $livLigne = new LivraisonLigne();
            $livLigne->setLivraison($liv);
            $livLigne->setProduit($ligne->getProduit());
            $livLigne->setConditionnement($ligne->getConditionnement());
            $livLigne->setQuantitePrevue($ligne->getQuantite());
            $livLigne->setQuantiteLivree($ligne->getQuantite());
            $liv->getLignes()->add($livLigne);
            $this->em->persist($livLigne);
        }

        return $liv;
    }
}
