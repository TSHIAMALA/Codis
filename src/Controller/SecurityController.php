<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Pays;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode est interceptée automatiquement par le firewall de Symfony.');
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));
            $username = trim($request->request->get('username', ''));
            $email = trim($request->request->get('email', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $plainPassword = $request->request->get('password', '');
            $paysId = $request->request->get('pays_id');

            if (empty($nom) || empty($username) || empty($plainPassword)) {
                $this->addFlash('danger', 'Veuillez remplir tous les champs obligatoires (*).');
                return $this->redirectToRoute('app_register');
            }

            if (mb_strlen($plainPassword) < UtilisateurController::MIN_PASSWORD_LENGTH) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins ' . UtilisateurController::MIN_PASSWORD_LENGTH . ' caractères.');
                return $this->redirectToRoute('app_register');
            }

            // Check if username or email exists
            $existingUser = $em->getRepository(Utilisateur::class)->findOneBy(['username' => $username]);
            if ($existingUser) {
                $this->addFlash('danger', 'Ce nom d\'utilisateur est déjà utilisé.');
                return $this->redirectToRoute('app_register');
            }

            if (!empty($email)) {
                $existingEmail = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
                if ($existingEmail) {
                    $this->addFlash('danger', 'Cette adresse email est déjà associée à un compte.');
                    return $this->redirectToRoute('app_register');
                }
            }

            $u = new Utilisateur();
            $u->setNom($nom);
            $u->setPrenom($prenom);
            $u->setUsername($username);
            $u->setEmail($email ?: null);
            $u->setTelephone($telephone ?: null);
            // Compte inactif et sans rôle : un administrateur doit valider la demande (Utilisateurs & Accès)
            $u->setActif(false);

            // Hash password
            $hashedPassword = $hasher->hashPassword($u, $plainPassword);
            $u->setPassword($hashedPassword);

            if ($paysId) {
                $pays = $em->getRepository(Pays::class)->find($paysId);
                if ($pays) $u->setPays($pays);
            }

            $em->persist($u);
            $em->flush();

            $this->addFlash('success', 'Demande d\'accès envoyée ! Un administrateur doit valider votre compte avant votre première connexion.');
            return $this->redirectToRoute('app_login');
        }

        $paysList = $em->getRepository(Pays::class)->findBy(['actif' => true]);

        return $this->render('security/register.html.twig', [
            'paysList' => $paysList,
        ]);
    }
}
