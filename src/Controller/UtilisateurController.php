<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Role;
use App\Entity\Pays;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN_PAYS', message: 'La gestion des comptes est réservée aux administrateurs.')]
#[Route('/utilisateurs')]
class UtilisateurController extends AbstractController
{
    public const MIN_PASSWORD_LENGTH = 8;

    #[Route('', name: 'app_utilisateur_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $utilisateurs = $em->getRepository(Utilisateur::class)->findBy([], ['id' => 'DESC']);
        $paysList = $em->getRepository(Pays::class)->findBy(['actif' => true]);

        // Demandes d'accès (inscription en ligne) : compte inactif sans aucun rôle
        $demandes = array_values(array_filter($utilisateurs, fn (Utilisateur $u) => $this->isDemandeAcces($u)));
        $comptes = array_values(array_filter($utilisateurs, fn (Utilisateur $u) => !$this->isDemandeAcces($u)));

        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $comptes,
            'demandes' => $demandes,
            'rolesList' => $this->getRolesAttribuables($em),
            'paysList' => $paysList,
        ]);
    }

    #[Route('/nouveau', name: 'app_utilisateur_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, AuditService $audit): Response
    {
        $nom = trim($request->request->get('nom', ''));
        $prenom = trim($request->request->get('prenom', ''));
        $username = trim($request->request->get('username', ''));
        $email = trim($request->request->get('email', ''));
        $telephone = trim($request->request->get('telephone', ''));
        $plainPassword = $request->request->get('password', '');
        $roleId = $request->request->get('role_id');
        $paysId = $request->request->get('pays_id');

        if (empty($nom) || empty($username) || empty($plainPassword) || empty($roleId)) {
            $this->addFlash('danger', 'Veuillez remplir tous les champs obligatoires (*).');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        if (mb_strlen($plainPassword) < self::MIN_PASSWORD_LENGTH) {
            $this->addFlash('danger', 'Le mot de passe doit contenir au moins ' . self::MIN_PASSWORD_LENGTH . ' caractères.');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        if ($em->getRepository(Utilisateur::class)->findOneBy(['username' => $username])) {
            $this->addFlash('danger', 'Le nom d\'utilisateur "' . $username . '" existe déjà.');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        if ($email !== '' && $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email])) {
            $this->addFlash('danger', 'L\'adresse email "' . $email . '" est déjà associée à un compte.');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        $roleObj = $this->findRoleAttribuable($em, $roleId);
        if (!$roleObj) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à attribuer ce rôle.');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        $u = new Utilisateur();
        $u->setNom($nom);
        $u->setPrenom($prenom);
        $u->setUsername($username);
        $u->setEmail($email ?: null);
        $u->setTelephone($telephone ?: null);
        $u->setActif(true);
        $u->setPassword($hasher->hashPassword($u, $plainPassword));
        $u->addRoleEntity($roleObj);

        if ($paysId) {
            $pays = $em->getRepository(Pays::class)->find($paysId);
            if ($pays) $u->setPays($pays);
        }

        $em->persist($u);
        $em->flush();
        $audit->log('utilisateur', 'CREATE', $u->getId(), null, ['username' => $u->getUsername(), 'role' => $roleObj->getCode()]);

        $this->addFlash('success', 'Compte utilisateur ' . $u->getUsername() . ' créé avec succès !');
        return $this->redirectToRoute('app_utilisateur_index');
    }

    #[Route('/{id}/activer', name: 'app_utilisateur_activer', methods: ['POST'])]
    public function activer(Utilisateur $u, Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        $roleObj = $this->findRoleAttribuable($em, $request->request->get('role_id'));
        if (!$roleObj) {
            $this->addFlash('danger', 'Choisissez un rôle valide pour activer le compte ' . $u->getUsername() . '.');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        $u->getRolesEntities()->clear();
        $u->addRoleEntity($roleObj);
        $u->setActif(true);
        $em->flush();
        $audit->log('utilisateur', 'ACTIVATE', $u->getId(), null, ['username' => $u->getUsername(), 'role' => $roleObj->getCode()]);

        $this->addFlash('success', 'Accès accordé à ' . $u->getNomComplet() . ' (' . $roleObj->getLibelle() . '). La personne peut maintenant se connecter.');
        return $this->redirectToRoute('app_utilisateur_index');
    }

    #[Route('/{id}/modifier', name: 'app_utilisateur_modifier', methods: ['POST'])]
    public function modifier(Utilisateur $u, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, AuditService $audit): Response
    {
        if (!$this->peutGerer($u)) {
            $this->addFlash('danger', 'Seul un Administrateur général peut modifier un compte Administrateur général.');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        $ancien = ['actif' => $u->isActif(), 'roles' => $u->getRoles()];

        $u->setNom($request->request->get('nom', $u->getNom()));
        $u->setPrenom($request->request->get('prenom', $u->getPrenom()));
        $u->setEmail($request->request->get('email', $u->getEmail()) ?: null);
        $u->setTelephone($request->request->get('telephone', $u->getTelephone()));

        $actif = $request->request->get('actif') === '1';
        if (!$actif && $u === $this->getUser()) {
            $this->addFlash('warning', 'Vous ne pouvez pas désactiver votre propre compte.');
            $actif = true;
        }
        $u->setActif($actif);

        $newPassword = $request->request->get('password');
        if (!empty($newPassword)) {
            if (mb_strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
                $this->addFlash('danger', 'Mot de passe non modifié : il doit contenir au moins ' . self::MIN_PASSWORD_LENGTH . ' caractères.');
                return $this->redirectToRoute('app_utilisateur_index');
            }
            $u->setPassword($hasher->hashPassword($u, $newPassword));
        }

        $roleId = $request->request->get('role_id');
        if ($roleId) {
            $roleObj = $this->findRoleAttribuable($em, $roleId);
            if (!$roleObj) {
                $this->addFlash('danger', 'Vous n\'êtes pas autorisé à attribuer ce rôle.');
                return $this->redirectToRoute('app_utilisateur_index');
            }
            $u->getRolesEntities()->clear();
            $u->addRoleEntity($roleObj);
        }

        $em->flush();
        $audit->log('utilisateur', 'UPDATE', $u->getId(), $ancien, [
            'actif' => $u->isActif(),
            'roles' => $u->getRoles(),
            'mot_de_passe_modifie' => !empty($newPassword),
        ]);

        $this->addFlash('info', 'Compte utilisateur ' . $u->getUsername() . ' mis à jour.');
        return $this->redirectToRoute('app_utilisateur_index');
    }

    #[Route('/{id}/supprimer', name: 'app_utilisateur_supprimer', methods: ['POST'])]
    public function supprimer(Utilisateur $u, EntityManagerInterface $em, AuditService $audit): Response
    {
        if ($u === $this->getUser()) {
            $this->addFlash('warning', 'Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('app_utilisateur_index');
        }
        if (!$this->peutGerer($u)) {
            $this->addFlash('danger', 'Seul un Administrateur général peut désactiver un compte Administrateur général.');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        // Une demande d'accès refusée n'a jamais eu accès ni donnée liée : elle est supprimée (tracée dans l'audit)
        if ($this->isDemandeAcces($u)) {
            $audit->log('utilisateur', 'REJECT', $u->getId(), ['username' => $u->getUsername(), 'nom' => $u->getNomComplet(), 'email' => $u->getEmail()], null);
            $this->addFlash('warning', 'Demande d\'accès de ' . $u->getNomComplet() . ' refusée.');
            $em->remove($u);
            $em->flush();
            return $this->redirectToRoute('app_utilisateur_index');
        }

        // Soft delete: set actif = false
        $u->setActif(false);
        $em->flush();
        $audit->log('utilisateur', 'DEACTIVATE', $u->getId(), null, ['username' => $u->getUsername()]);

        $this->addFlash('warning', 'Compte utilisateur ' . $u->getUsername() . ' désactivé (suppression logique).');
        return $this->redirectToRoute('app_utilisateur_index');
    }

    private function isDemandeAcces(Utilisateur $u): bool
    {
        return !$u->isActif() && $u->getRolesEntities()->isEmpty();
    }

    /** Un administrateur pays ne peut ni créer ni gérer d'Administrateur général. */
    private function peutGerer(Utilisateur $u): bool
    {
        return $this->isGranted('ROLE_SUPER_ADMIN') || !in_array('ROLE_SUPER_ADMIN', $u->getRoles(), true);
    }

    /** @return Role[] */
    private function getRolesAttribuables(EntityManagerInterface $em): array
    {
        $roles = $em->getRepository(Role::class)->findBy([], ['id' => 'ASC']);
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $roles;
        }

        return array_values(array_filter($roles, fn (Role $r) => $r->getCode() !== 'SUPER_ADMIN'));
    }

    private function findRoleAttribuable(EntityManagerInterface $em, mixed $roleId): ?Role
    {
        foreach ($this->getRolesAttribuables($em) as $r) {
            if ((string) $r->getId() === (string) $roleId) {
                return $r;
            }
        }

        return null;
    }
}
