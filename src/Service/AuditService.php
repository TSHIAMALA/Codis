<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack
    ) {}

    public function log(string $tableName, string $action, ?int $refId = null, ?array $oldVal = null, ?array $newVal = null): void
    {
        $user = $this->security->getUser();
        $req = $this->requestStack->getCurrentRequest();

        $log = new AuditLog();
        if ($user instanceof Utilisateur) {
            $log->setUtilisateur($user);
        }
        $log->setTableName($tableName);
        $log->setAction($action);
        $log->setReferenceId($refId);
        $log->setAncienneValeur($oldVal);
        $log->setNouvelleValeur($newVal);
        $log->setAdresseIp($req ? $req->getClientIp() : '127.0.0.1');

        $this->em->persist($log);
        $this->em->flush();
    }
}