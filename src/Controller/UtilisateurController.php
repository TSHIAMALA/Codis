<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Role;
use App\Entity\Pays;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/utilisateurs')]
class UtilisateurController extends AbstractController
{
    #[Route('', name: 'app_utilisateur_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $utilisateurs = $em->getRepository(Utilisateur::class)->findBy([], ['id' => 'DESC']);
        $roles = $em->getRepository(Role::class)->findAll();
        $paysList = $em->getRepository(Pays::class)->findBy(['actif' => true]);

        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurs,
            'rolesList' => $roles,
            'paysList' => $paysList,
        ]);
    }

    #[Route('/nouveau', name: 'app_utilisateur_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $nom = trim($request->request->get('nom', ''));
        $prenom = trim($request->request->get('prenom', ''));
        $username = trim($request->request->get('username', ''));
        $email = trim($request->request->get('email', ''));
        $telephone = trim($request->request->get('telephone', ''));
        $plainPassword = $request->request->get('password', '');
        $roleId = $request->request->get('role_id');
        $paysId = $request->request->get('pays_id');

        if (empty($nom) || empty($username) || empty($plainPassword)) {
            $this->addFlash('danger', 'Veuillez remplir tous les champs obligatoires (*).');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        $existing = $em->getRepository(Utilisateur::class)->findOneBy(['username' => $username]);
        if ($existing) {
            $this->addFlash('danger', 'Le nom d\'utilisateur "' . $username . '" existe déjà.');
            return $this->redirectToRoute('app_utilisateur_index');
        }

        $u = new Utilisateur();
        $u->setNom($nom);
        $u->setPrenom($prenom);
        $u->setUsername($username);
        $u->setEmail($email ?: null);
        $u->setTelephone($telephone ?: null);
        $u->setActif(true);

        $hashedPassword = $hasher->hashPassword($u, $plainPassword);
        $u->setPassword($hashedPassword);

        if ($paysId) {
            $pays = $em->getRepository(Pays::class)->find($paysId);
            if ($pays) $u->setPays($pays);
        }

        if ($roleId) {
            $roleObj = $em->getRepository(Role::class)->find($roleId);
            if ($roleObj) $u->addRoleEntity($roleObj);
        }

        $em->persist($u);
        $em->flush();

        $this->addFlash('success', 'Compte utilisateur ' . $u->getUsername() . ' créé avec succès !');
        return $this->redirectToRoute('app_utilisateur_index');
    }

    #[Route('/{id}/modifier', name: 'app_utilisateur_modifier', methods: ['POST'])]
    public function modifier(Utilisateur $u, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $u->setNom($request->request->get('nom', $u->getNom()));
        $u->setPrenom($request->request->get('prenom', $u->getPrenom()));
        $u->setEmail($request->request->get('email', $u->getEmail()));
        $u->setTelephone($request->request->get('telephone', $u->getTelephone()));
        $u->setActif($request->request->get('actif') === '1');

        $newPassword = $request->request->get('password');
        if (!empty($newPassword)) {
            $u->setPassword($hasher->hashPassword($u, $newPassword));
        }

        $roleId = $request->request->get('role_id');
        if ($roleId) {
            $u->getRolesEntities()->clear();
            $roleObj = $em->getRepository(Role::class)->find($roleId);
            if ($roleObj) $u->addRoleEntity($roleObj);
        }

        $em->flush();
        $this->addFlash('info', 'Compte utilisateur ' . $u->getUsername() . ' mis à jour.');
        return $this->redirectToRoute('app_utilisateur_index');
    }

    #[Route('/{id}/supprimer', name: 'app_utilisateur_supprimer', methods: ['POST'])]
    public function supprimer(Utilisateur $u, EntityManagerInterface $em): Response
    {
        // Soft delete: set actif = false
        $u->setActif(false);
        $em->flush();

        $this->addFlash('warning', 'Compte utilisateur ' . $u->getUsername() . ' désactivé (suppression logique).');
        return $this->redirectToRoute('app_utilisateur_index');
    }
}
