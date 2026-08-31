<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Livraison;
use App\Entity\LivraisonLigne;
use App\Entity\Grossiste;
use App\Entity\Detailant;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class OrderManagerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $auditService
    ) {}

    public function generateOrderNumber(): string
    {
        return 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function generateDeliveryNumber(): string
    {
        return 'BL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function verifierLimiteCredit(Grossiste $grossiste, float $nouveauMontant): array
    {
        $limite = (float) $grossiste->getLimiteCredit();
        // Calculer l'encours actuel
        $commandes = $this->em->getRepository(Commande::class)->findBy([
            'grossiste' => $grossiste,
            'statut' => ['VALIDEE', 'EN_PREPARATION', 'EXPEDIEE', 'LIVREE']
        ]);

        $encours = 0.0;
        foreach ($commandes as $cmd) {
            $totalCmd = (float) $cmd->getMontantTotal();
            $paye = 0.0;
            foreach ($cmd->getPaiements() as $p) {
                if ($p->getStatut() === 'VALIDE') {
                    $paye += (float) $p->getMontant();
                }
            }
            $encours += max(0, $totalCmd - $paye);
        }

        $autorise = ($encours + $nouveauMontant) <= $limite || $limite <= 0;
        return [
            'autorise' => $autorise,
            'limite' => $limite,
            'encours' => $encours,
            'disponible' => max(0, $limite - $encours),
        ];
    }

    public function validerCommande(Commande $cmd, ?Utilisateur $valideur = null): Livraison
    {
        $cmd->setStatut('VALIDEE');

        // Créer automatiquement le bon de livraison
        $liv = new Livraison();
        $liv->setNumero($this->generateDeliveryNumber());
        $liv->setCommande($cmd);
        $liv->setDepot($cmd->getDepot());
        $liv->setStatut('PREPARATION');
        $liv->setDatePrevue(new \DateTime('+1 day'));
        $liv->setObservation('Génération automatique après validation de la commande ' . $cmd->getNumero());

        $this->em->persist($liv);

        foreach ($cmd->getLignes() as $ligne) {
            $livLigne = new LivraisonLigne();
            $livLigne->setLivraison($liv);
            $livLigne->setProduit($ligne->getProduit());
            $livLigne->setConditionnement($ligne->getConditionnement());
            $livLigne->setQuantitePrevue($ligne->getQuantite());
            $livLigne->setQuantiteLivree($ligne->getQuantite());
            $this->em->persist($livLigne);
        }

        $this->em->flush();
        $this->auditService->log('commande', 'VALIDATE', $cmd->getId(), null, [
            'numero' => $cmd->getNumero(),
            'valide_par' => $valideur ? $valideur->getUsername() : 'System',
        ]);

        return $liv;
    }
}