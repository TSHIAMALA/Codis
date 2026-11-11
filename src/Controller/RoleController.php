<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Security\MatriceAcces;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Rôles & Permissions : consultation de ce que chaque rôle peut faire, et de qui le porte.
 * Les droits sont fixés dans le code (security.yaml + contrôleurs) ; seul le libellé d'un rôle se modifie ici.
 */
#[IsGranted('ROLE_ADMIN_PAYS', message: 'La consultation des rôles est réservée aux administrateurs.')]
#[Route('/roles')]
class RoleController extends AbstractController
{
    #[Route('', name: 'app_role_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, MatriceAcces $matrice): Response
    {
        $roles = $em->getRepository(Role::class)->findBy([], ['id' => 'ASC']);

        $utilisateurs = $em->createQueryBuilder()
            ->select('u', 'r')
            ->from(Utilisateur::class, 'u')
            ->join('u.rolesEntities', 'r')
            ->orderBy('u.nom')
            ->getQuery()->getResult();

        $porteurs = [];
        foreach ($utilisateurs as $u) {
            foreach ($u->getRolesEntities() as $r) {
                $porteurs[$r->getId()][] = $u;
            }
        }

        $droits = [];
        $accesWeb = [];
        foreach ($roles as $r) {
            $droits[$r->getId()] = $matrice->droitsDuRole($r->getCode());
            $accesWeb[$r->getId()] = $matrice->aAccesWeb($r->getCode());
        }

        return $this->render('role/index.html.twig', [
            'roles' => $roles,
            'modules' => MatriceAcces::MODULES,
            'droits' => $droits,
            'accesWeb' => $accesWeb,
            'porteurs' => $porteurs,
        ]);
    }

    #[IsGranted('ROLE_SUPER_ADMIN', message: 'Seul un Administrateur général peut renommer un rôle.')]
    #[Route('/{id}/libelle', name: 'app_role_libelle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function libelle(Role $role, Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        $libelle = trim((string) $request->request->get('libelle'));
        if ($libelle === '' || mb_strlen($libelle) > 100) {
            $this->addFlash('danger', 'Le libellé du rôle doit contenir entre 1 et 100 caractères.');
            return $this->redirectToRoute('app_role_index');
        }

        $ancien = $role->getLibelle();
        if ($libelle !== $ancien) {
            $role->setLibelle($libelle);
            $em->flush();
            $audit->log('role', 'UPDATE', $role->getId(), ['libelle' => $ancien], ['libelle' => $libelle]);
        }

        $this->addFlash('success', 'Rôle ' . $role->getCode() . ' renommé en « ' . $libelle . ' ».');
        return $this->redirectToRoute('app_role_index');
    }
}
