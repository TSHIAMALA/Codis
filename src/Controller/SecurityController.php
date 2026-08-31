<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Pays;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
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
            $u->setActif(true);

            // Hash password
            $hashedPassword = $hasher->hashPassword($u, $plainPassword);
            $u->setPassword($hashedPassword);

            if ($paysId) {
                $pays = $em->getRepository(Pays::class)->find($paysId);
                if ($pays) $u->setPays($pays);
            }

            // Assign default role (COMMERCIAL or USER)
            $roleUser = $em->getRepository(Role::class)->findOneBy(['code' => 'COMMERCIAL']);
            if ($roleUser) {
                $u->addRoleEntity($roleUser);
            }

            $em->persist($u);
            $em->flush();

            $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        $paysList = $em->getRepository(Pays::class)->findBy(['actif' => true]);

        return $this->render('security/register.html.twig', [
            'paysList' => $paysList,
        ]);
    }
}
