<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $tableName = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $action = null; // INSERT, UPDATE, DELETE, LOGIN, VALIDATE

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $referenceId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $ancienneValeur = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $nouvelleValeur = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $adresseIp = null;

    #[ORM\Column(type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getTableName(): ?string { return $this->tableName; }
    public function setTableName(string $t): self { $this->tableName = $t; return $this; }
    public function getAction(): ?string { return $this->action; }
    public function setAction(string $a): self { $this->action = $a; return $this; }
    public function getReferenceId(): ?int { return $this->referenceId; }
    public function setReferenceId(?int $r): self { $this->referenceId = $r; return $this; }
    public function getAncienneValeur(): ?array { return $this->ancienneValeur; }
    public function setAncienneValeur(?array $a): self { $this->ancienneValeur = $a; return $this; }
    public function getNouvelleValeur(): ?array { return $this->nouvelleValeur; }
    public function setNouvelleValeur(?array $n): self { $this->nouvelleValeur = $n; return $this; }
    public function getAdresseIp(): ?string { return $this->adresseIp; }
    public function setAdresseIp(?string $ip): self { $this->adresseIp = $ip; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}