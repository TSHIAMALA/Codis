<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'id', nullable: false)]
    private ?Commande $commande = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $montant = '0.00';

    #[ORM\Column(type: 'string', length: 30, options: ['default' => 'ESPECES'])]
    private string $modePaiement = 'ESPECES'; // ESPECES, VIREMENT, MOBILE_MONEY, CHEQUE, CREDIT

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'VALIDE'])]
    private string $statut = 'VALIDE'; // EN_ATTENTE, VALIDE, REJETE

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    public function __construct()
    {
        $this->datePaiement = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $c): self { $this->commande = $c; return $this; }
    public function getDatePaiement(): ?\DateTimeInterface { return $this->datePaiement; }
    public function setDatePaiement(?\DateTimeInterface $d): self { $this->datePaiement = $d; return $this; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $m): self { $this->montant = $m; return $this; }
    public function getModePaiement(): string { return $this->modePaiement; }
    public function setModePaiement(string $m): self { $this->modePaiement = $m; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $r): self { $this->reference = $r; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $o): self { $this->observation = $o; return $this; }
}