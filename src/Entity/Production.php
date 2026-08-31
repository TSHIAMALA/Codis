<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'production')]
class Production
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Usine::class)]
    #[ORM\JoinColumn(name: 'usine_id', referencedColumnName: 'id', nullable: false)]
    private ?Usine $usine = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private ?string $numero = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateProduction = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'BROUILLON'])]
    private string $statut = 'BROUILLON'; // BROUILLON, VALIDEE, ANNULEE

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'production', targetEntity: ProductionLigne::class, cascade: ['persist', 'remove'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->dateProduction = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUsine(): ?Usine { return $this->usine; }
    public function setUsine(?Usine $u): self { $this->usine = $u; return $this; }
    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $n): self { $this->numero = $n; return $this; }
    public function getDateProduction(): ?\DateTimeInterface { return $this->dateProduction; }
    public function setDateProduction(?\DateTimeInterface $d): self { $this->dateProduction = $d; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }
    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $o): self { $this->observation = $o; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getLignes(): Collection { return $this->lignes; }
}