<?php

namespace App\Controller;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/audit')]
class AuditController extends AbstractController
{
    #[Route('', name: 'app_audit_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $logs = $em->getRepository(AuditLog::class)->findBy([], ['id' => 'DESC'], 100);

        return $this->render('audit/index.html.twig', [
            'logs' => $logs,
        ]);
    }
}