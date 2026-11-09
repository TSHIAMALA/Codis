<?php

namespace App\Security\Voter;

use App\Entity\AgentCommercial;
use App\Entity\DemandeConsignation;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Circuit de validation des prélèvements DR :
 *  - Niveau 1 : le Superviseur du DR (ou un ASM) ;
 *  - Niveau 2 : un ASM, différent de la personne ayant validé le niveau 1 ;
 *  - Clôture  : le DR lui-même, sa hiérarchie ou le gestionnaire de dépôt ;
 *  - Rejet    : toute personne habilitée à valider le niveau en attente.
 * Les administrateurs pays peuvent agir à chaque niveau (structure sans ASM/Superviseur).
 */
class ConsignationVoter extends Voter
{
    public const VALIDER_SUPERVISEUR = 'CONSIGNATION_VALIDER_SUPERVISEUR';
    public const VALIDER_ASM = 'CONSIGNATION_VALIDER_ASM';
    public const CLOTURER = 'CONSIGNATION_CLOTURER';
    public const REJETER = 'CONSIGNATION_REJETER';

    /** Agent du dernier utilisateur évalué : une liste de demandes appelle le voter plusieurs fois par ligne. */
    private ?int $agentUtilisateurId = null;
    private ?AgentCommercial $agent = null;

    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
    ) {}

    private function getAgent(Utilisateur $user): ?AgentCommercial
    {
        if ($this->agentUtilisateurId !== $user->getId()) {
            $this->agentUtilisateurId = $user->getId();
            $this->agent = $this->em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $user, 'actif' => true]);
        }

        return $this->agent;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof DemandeConsignation
            && in_array($attribute, [self::VALIDER_SUPERVISEUR, self::VALIDER_ASM, self::CLOTURER, self::REJETER], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof Utilisateur) {
            return false;
        }

        /** @var DemandeConsignation $c */
        $c = $subject;
        $agent = $this->getAgent($user);
        $isAdmin = $this->security->isGranted('ROLE_ADMIN_PAYS');
        $isAsm = $agent && $agent->getTypeAgent() === 'ASM';

        if ($attribute === self::REJETER) {
            return $c->getStatut() === 'SOUMISE'
                ? $this->voteOnAttribute(self::VALIDER_SUPERVISEUR, $c, $token)
                : $this->voteOnAttribute(self::VALIDER_ASM, $c, $token);
        }

        switch ($attribute) {
            case self::VALIDER_SUPERVISEUR:
                if ($c->getStatut() !== 'SOUMISE') {
                    return false;
                }
                $isSuperviseurDuDr = $agent && $c->getDr()?->getSuperviseur() === $agent;
                return $isAdmin || $isAsm || $isSuperviseurDuDr;

            case self::VALIDER_ASM:
                if ($c->getStatut() !== 'VALIDEE_SUPERVISEUR') {
                    return false;
                }
                // Séparation des tâches : le valideur N2 ne peut pas être celui du N1.
                if ($agent && $c->getSuperviseur() === $agent) {
                    return false;
                }
                return $isAdmin || $isAsm;

            case self::CLOTURER:
                if ($c->getStatut() !== 'VALIDEE_ASM') {
                    return false;
                }
                $isDr = $agent && $c->getDr() === $agent;
                $isHierarchie = $agent && ($c->getDr()?->getSuperviseur() === $agent || $isAsm);
                return $isAdmin || $isDr || $isHierarchie || $this->security->isGranted('ROLE_GESTIONNAIRE_DEPOT');
        }

        return false;
    }
}
