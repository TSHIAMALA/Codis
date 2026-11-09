<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Pagination\Page;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN', message: 'L\'audit est réservé à l\'administration générale.')]
#[Route('/audit')]
class AuditController extends AbstractController
{
    #[Route('', name: 'app_audit_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $qb = $em->createQueryBuilder()
            ->select('a', 'u')
            ->from(AuditLog::class, 'a')
            ->leftJoin('a.utilisateur', 'u')
            ->orderBy('a.id', 'DESC');
        if ($table = $request->query->get('table')) {
            $qb->andWhere('a.tableName = :table')->setParameter('table', $table);
        }
        $logs = Page::depuisRequete($qb, $request, 50);
        $tables = array_column($em->createQueryBuilder()->select('DISTINCT a.tableName AS t')->from(AuditLog::class, 'a')->orderBy('t')->getQuery()->getArrayResult(), 't');

        return $this->render('audit/index.html.twig', [
            'logs' => $logs,
            'tables' => $tables,
        ]);
    }
}