<?php

namespace App\Service;

use App\Entity\AgentCommercial;
use App\Entity\Conditionnement;
use App\Entity\DemandeConsignation;
use App\Entity\DemandeConsignationLigne;
use App\Entity\EmplacementStock;
use App\Entity\Stock;
use App\Entity\Utilisateur;
use App\Exception\OperationImpossibleException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Distribution directe : Dépôt ➔ (validation ASM) ➔ Stock DR ➔ (clôture) ➔ Vendu / Retour dépôt.
 */
class ConsignationService
{
    public const REF_PRELEVEMENT = 'CONSIGNATION_DR';
    public const REF_RETOUR = 'RETOUR_INVENDU_DR';
    public const REF_VENTE = 'VENTE_TOURNEE_DR';
    private const EPSILON = 0.0005;

    public function __construct(
        private EntityManagerInterface $em,
        private StockManagerService $stockService,
        private TarifResolver $tarifResolver,
        private AuditService $auditService,
        #[Target('consignation')] private WorkflowInterface $consignationWorkflow,
    ) {}

    public function validerSuperviseur(DemandeConsignation $c, ?AgentCommercial $valideur): void
    {
        $this->appliquer($c, 'valider_superviseur');
        $c->setDateValidationSuperviseur(new \DateTime());
        $c->setSuperviseur($valideur);
        $this->em->flush();
    }

    /**
     * Validation finale : le stock quitte le dépôt et passe sur l'emplacement du DR (même lot).
     *
     * @throws OperationImpossibleException si un produit manque au dépôt (aucune sortie n'est alors effectuée)
     */
    public function validerAsm(DemandeConsignation $c, ?AgentCommercial $valideur, ?Utilisateur $user): void
    {
        if (!$this->consignationWorkflow->can($c, 'valider_asm')) {
            throw new OperationImpossibleException('La demande ' . $c->getNumero() . ' doit d\'abord être approuvée par le Superviseur.');
        }

        $depot = $this->stockService->getEmplacementDepot($c->getDepot());
        if (!$depot) {
            throw new OperationImpossibleException('Aucun emplacement de stock actif pour le dépôt ' . ($c->getDepot()?->getNom() ?? '(non renseigné)') . '.');
        }

        $this->em->wrapInTransaction(function () use ($c, $valideur, $user, $depot) {
            $empDr = $this->stockService->getOrCreateEmplacementDr($c->getDr());
            foreach ($c->getLignes() as $ligne) {
                $this->completerConditionnement($ligne, $depot);
                $this->stockService->sortir(
                    'TRANSFERT', $ligne->getProduit(), $ligne->getConditionnement(), (float) $ligne->getQuantiteAccordee(),
                    $depot, $empDr, self::REF_PRELEVEMENT, $c->getId(), $user, 'Prélèvement consignation DR ' . $c->getNumero()
                );
            }

            $this->consignationWorkflow->apply($c, 'valider_asm');
            $c->setDateValidationAsm(new \DateTime());
            $c->setAsm($valideur);
            $this->em->flush();
        });
    }

    public function rejeter(DemandeConsignation $c, string $motif): void
    {
        $this->appliquer($c, 'rejeter');
        $c->setObservation(trim(($c->getObservation() ? $c->getObservation() . "\n" : '') . 'Rejet : ' . $motif));
        $this->em->flush();
        $this->auditService->log('demande_consignation', 'REJECT', $c->getId(), null, ['numero' => $c->getNumero(), 'motif' => $motif]);
    }

    /**
     * Prix unitaire attendu par ligne (tarif grossiste de la région du dépôt), pour contrôler le cash.
     *
     * @return array<int, float|null> id de ligne => prix (null si aucun tarif)
     */
    public function prixAttendus(DemandeConsignation $c): array
    {
        $prix = [];
        foreach ($c->getLignes() as $ligne) {
            $tarif = $ligne->getConditionnement()
                ? $this->tarifResolver->resoudre($ligne->getProduit(), $ligne->getConditionnement(), 'GROSSISTE', null, null, $c->getDepot()?->getRegion())
                : null;
            $prix[$ligne->getId()] = $tarif ? $tarif['prix'] : null;
        }

        return $prix;
    }

    /**
     * Clôture de journée : chaque ligne doit être justifiée (vendu + retourné = confié).
     * Les invendus retournent au dépôt, les ventes sortent du stock DR. Un écart entre le cash
     * déclaré et la valeur des ventes doit être justifié.
     *
     * @param array<int, array{vendue: float, retournee: float}> $quantites par id de ligne
     * @return array{attendu: float|null, ecart: float|null}
     * @throws OperationImpossibleException
     */
    public function cloturer(DemandeConsignation $c, float $cash, array $quantites, ?string $justification, ?Utilisateur $user): array
    {
        if (!$this->consignationWorkflow->can($c, 'cloturer')) {
            throw new OperationImpossibleException('La demande ' . $c->getNumero() . ' n\'est pas en tournée (statut : ' . $c->getStatut() . ').');
        }
        if ($cash < 0) {
            throw new OperationImpossibleException('Le cash encaissé ne peut pas être négatif.');
        }

        $prix = $this->prixAttendus($c);
        $attendu = 0.0;
        $tousPrixConnus = true;
        foreach ($c->getLignes() as $ligne) {
            $q = $quantites[$ligne->getId()] ?? null;
            $confiee = (float) $ligne->getQuantiteAccordee();
            if ($q === null || $q['vendue'] < 0 || $q['retournee'] < 0 || abs($q['vendue'] + $q['retournee'] - $confiee) > self::EPSILON) {
                throw new OperationImpossibleException(sprintf(
                    'Clôture refusée pour %s : vendu (%s) + invendus (%s) doit être égal à la quantité confiée (%s).',
                    $ligne->getProduit()->getNom(),
                    $q['vendue'] ?? 0, $q['retournee'] ?? 0, $confiee
                ));
            }
            if ($prix[$ligne->getId()] === null) {
                $tousPrixConnus = false;
            } else {
                $attendu += $q['vendue'] * $prix[$ligne->getId()];
            }
        }

        $attendu = $tousPrixConnus ? round($attendu, 2) : null;
        $ecart = $attendu !== null ? round($cash - $attendu, 2) : null;
        $justification = trim((string) $justification);
        if ($ecart !== null && abs($ecart) >= 0.01 && $justification === '') {
            throw new OperationImpossibleException(sprintf(
                'Écart de caisse de %s $ (cash %s $ / ventes attendues %s $) : une justification est obligatoire.',
                number_format($ecart, 2, ',', ' '), number_format($cash, 2, ',', ' '), number_format($attendu, 2, ',', ' ')
            ));
        }

        $depot = $this->stockService->getEmplacementDepot($c->getDepot());
        if (!$depot) {
            throw new OperationImpossibleException('Aucun emplacement de stock actif pour le dépôt ' . ($c->getDepot()?->getNom() ?? '(non renseigné)') . '.');
        }

        $this->em->wrapInTransaction(function () use ($c, $cash, $quantites, $justification, $attendu, $ecart, $user, $depot) {
            $empDr = $this->stockService->getOrCreateEmplacementDr($c->getDr());
            foreach ($c->getLignes() as $ligne) {
                $q = $quantites[$ligne->getId()];
                $ligne->setQuantiteVendue(number_format($q['vendue'], 3, '.', ''));
                $ligne->setQuantiteRetournee(number_format($q['retournee'], 3, '.', ''));

                if ($q['retournee'] > 0) {
                    $this->stockService->sortir('RETOUR', $ligne->getProduit(), $ligne->getConditionnement(), $q['retournee'], $empDr, $depot,
                        self::REF_RETOUR, $c->getId(), $user, 'Retour invendus de fin de journée DR ' . $c->getNumero());
                }
                if ($q['vendue'] > 0) {
                    $this->stockService->sortir('VENTE', $ligne->getProduit(), $ligne->getConditionnement(), $q['vendue'], $empDr, null,
                        self::REF_VENTE, $c->getId(), $user, 'Ventes tournée DR ' . $c->getNumero());
                }
            }

            $c->setCashJustifie(number_format($cash, 2, '.', ''));
            if ($justification !== '') {
                $c->setObservation(trim(($c->getObservation() ? $c->getObservation() . "\n" : '') . 'Écart de caisse ' . number_format((float) $ecart, 2, ',', ' ') . ' $ : ' . $justification));
            }
            $this->consignationWorkflow->apply($c, 'cloturer');
            $this->em->flush();
            $this->auditService->log('demande_consignation', 'CLOSE', $c->getId(), null, [
                'numero' => $c->getNumero(), 'cash' => $cash, 'attendu' => $attendu, 'ecart' => $ecart,
            ]);
        });

        return ['attendu' => $attendu, 'ecart' => $ecart];
    }

    /**
     * Les anciennes demandes n'ont pas de conditionnement : on retient l'unique conditionnement
     * en stock au dépôt pour ce produit, sinon on demande de recréer la demande.
     */
    private function completerConditionnement(DemandeConsignationLigne $ligne, EmplacementStock $empDepot): void
    {
        if ($ligne->getConditionnement()) {
            return;
        }

        $conds = $this->em->createQueryBuilder()
            ->select('DISTINCT c')
            ->from(Conditionnement::class, 'c')
            ->join(Stock::class, 's', 'WITH', 's.conditionnement = c')
            ->where('s.emplacement = :emp AND s.produit = :p AND s.quantiteDisponible > 0')
            ->setParameter('emp', $empDepot)
            ->setParameter('p', $ligne->getProduit())
            ->getQuery()->getResult();

        if (count($conds) !== 1) {
            throw new OperationImpossibleException(sprintf(
                'La demande ne précise pas le conditionnement de %s et le dépôt en stocke %s. Rejetez-la et créez une nouvelle demande en précisant le conditionnement.',
                $ligne->getProduit()->getNom(),
                count($conds) === 0 ? 'aucun' : 'plusieurs'
            ));
        }
        $ligne->setConditionnement($conds[0]);
    }

    private function appliquer(DemandeConsignation $c, string $transition): void
    {
        if (!$this->consignationWorkflow->can($c, $transition)) {
            throw new OperationImpossibleException('Action impossible sur la demande ' . $c->getNumero() . ' (statut : ' . $c->getStatut() . ').');
        }
        $this->consignationWorkflow->apply($c, $transition);
    }
}
