<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'transfert_stock')]
class TransfertStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private ?string $numero = null;

    #[ORM\ManyToOne(targetEntity: EmplacementStock::class)]
    #[ORM\JoinColumn(name: 'emplacement_source_id', referencedColumnName: 'id', nullable: false)]
    private ?EmplacementStock $emplacementSource = null;

    #[ORM\ManyToOne(targetEntity: EmplacementStock::class)]
    #[ORM\JoinColumn(name: 'emplacement_destination_id', referencedColumnName: 'id', nullable: false)]
    private ?EmplacementStock $emplacementDestination = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateDemande = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateExpedition = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateReception = null;

    #[ORM\Column(type: 'string', length: 30, options: ['default' => 'BROUILLON'])]
    private string $statut = 'BROUILLON'; // BROUILLON, VALIDE, PREPARE, EXPEDIE, EN_TRANSIT, RECEPTIONNE, ANNULE

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\OneToMany(mappedBy: 'transfert', targetEntity: TransfertStockLigne::class, cascade: ['persist', 'remove'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->dateDemande = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $n): self { $this->numero = $n; return $this; }
    public function getEmplacementSource(): ?EmplacementStock { return $this->emplacementSource; }
    public function setEmplacementSource(?EmplacementStock $e): self { $this->emplacementSource = $e; return $this; }
    public function getEmplacementDestination(): ?EmplacementStock { return $this->emplacementDestination; }
    public function setEmplacementDestination(?EmplacementStock $e): self { $this->emplacementDestination = $e; return $this; }
    public function getDateDemande(): ?\DateTimeInterface { return $this->dateDemande; }
    public function setDateDemande(?\DateTimeInterface $d): self { $this->dateDemande = $d; return $this; }
    public function getDateExpedition(): ?\DateTimeInterface { return $this->dateExpedition; }
    public function setDateExpedition(?\DateTimeInterface $d): self { $this->dateExpedition = $d; return $this; }
    public function getDateReception(): ?\DateTimeInterface { return $this->dateReception; }
    public function setDateReception(?\DateTimeInterface $d): self { $this->dateReception = $d; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $o): self { $this->observation = $o; return $this; }
    public function getLignes(): Collection { return $this->lignes; }
}