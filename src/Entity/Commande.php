<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'commande')]
#[ORM\Index(name: 'idx_commande_date', columns: ['date_commande'])]
#[ORM\Index(name: 'idx_commande_statut', columns: ['statut'])]
class Commande
{
    /** Commandes comptées dans le chiffre d'affaires : tableau de bord et filtre CHIFFRE_AFFAIRES de la liste. */
    public const STATUTS_CHIFFRE_AFFAIRES = ['VALIDEE', 'EXPEDIEE', 'LIVREE', 'RECEPTIONNEE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private ?string $numero = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $typeCommande = 'GROSSISTE'; // GROSSISTE, DETAILLANT

    #[ORM\ManyToOne(targetEntity: Grossiste::class)]
    #[ORM\JoinColumn(name: 'grossiste_id', referencedColumnName: 'id', nullable: true)]
    private ?Grossiste $grossiste = null;

    #[ORM\ManyToOne(targetEntity: Detailant::class)]
    #[ORM\JoinColumn(name: 'detailant_id', referencedColumnName: 'id', nullable: true)]
    private ?Detailant $detailant = null;

    #[ORM\ManyToOne(targetEntity: Delegue::class)]
    #[ORM\JoinColumn(name: 'delegue_id', referencedColumnName: 'id', nullable: true)]
    private ?Delegue $delegue = null;

    #[ORM\ManyToOne(targetEntity: AgentCommercial::class)]
    #[ORM\JoinColumn(name: 'agent_id', referencedColumnName: 'id', nullable: true)]
    private ?AgentCommercial $agent = null;

    #[ORM\ManyToOne(targetEntity: Depot::class)]
    #[ORM\JoinColumn(name: 'depot_id', referencedColumnName: 'id', nullable: true)]
    private ?Depot $depot = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(type: 'string', length: 30, options: ['default' => 'BROUILLON'])]
    private string $statut = 'BROUILLON'; // BROUILLON, SOUMISE, VALIDEE, REJETEE, EN_PREPARATION, EXPEDIEE, LIVREE, RECEPTIONNEE, ANNULEE

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, options: ['default' => '0.00'])]
    private ?string $montantTotal = '0.00';

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true)]
    private ?Utilisateur $createdBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeLigne::class, cascade: ['persist', 'remove'])]
    private Collection $lignes;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: Livraison::class)]
    private Collection $livraisons;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: Paiement::class)]
    private Collection $paiements;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->dateCommande = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $n): self { $this->numero = $n; return $this; }
    public function getTypeCommande(): string { return $this->typeCommande; }
    public function setTypeCommande(string $t): self { $this->typeCommande = $t; return $this; }
    public function getGrossiste(): ?Grossiste { return $this->grossiste; }
    public function setGrossiste(?Grossiste $g): self { $this->grossiste = $g; return $this; }
    public function getDetailant(): ?Detailant { return $this->detailant; }
    public function setDetailant(?Detailant $d): self { $this->detailant = $d; return $this; }
    public function getDelegue(): ?Delegue { return $this->delegue; }
    public function setDelegue(?Delegue $d): self { $this->delegue = $d; return $this; }
    public function getAgent(): ?AgentCommercial { return $this->agent; }
    public function setAgent(?AgentCommercial $a): self { $this->agent = $a; return $this; }
    public function getDepot(): ?Depot { return $this->depot; }
    public function setDepot(?Depot $d): self { $this->depot = $d; return $this; }
    public function getDateCommande(): ?\DateTimeInterface { return $this->dateCommande; }
    public function setDateCommande(?\DateTimeInterface $d): self { $this->dateCommande = $d; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }
    public function getMontantTotal(): ?string { return $this->montantTotal; }
    public function setMontantTotal(string $m): self { $this->montantTotal = $m; return $this; }
    public function getCreatedBy(): ?Utilisateur { return $this->createdBy; }
    public function setCreatedBy(?Utilisateur $u): self { $this->createdBy = $u; return $this; }
    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $o): self { $this->observation = $o; return $this; }
    public function getLignes(): Collection { return $this->lignes; }
    public function getLivraisons(): Collection { return $this->livraisons; }
    public function getPaiements(): Collection { return $this->paiements; }

    public function getClientNom(): string
    {
        if ($this->grossiste) return $this->grossiste->getRaisonSociale();
        if ($this->detailant) return $this->detailant->getNom();
        return 'Client Divers';
    }
}