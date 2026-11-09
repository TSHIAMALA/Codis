<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mouvement_stock')]
#[ORM\Index(name: 'idx_mvt_date', columns: ['date_mouvement'])]
#[ORM\Index(name: 'idx_mvt_type', columns: ['type_mouvement'])]
#[ORM\Index(name: 'idx_mvt_reference', columns: ['reference_type', 'reference_id'])]
class MouvementStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateMouvement = null;

    #[ORM\Column(type: 'string', length: 30)]
    private string $typeMouvement = 'ENTREE'; // PRODUCTION, ENTREE, SORTIE, TRANSFERT, VENTE, RETOUR, AJUSTEMENT, INVENTAIRE

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(name: 'produit_id', referencedColumnName: 'id', nullable: false)]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(targetEntity: Lot::class)]
    #[ORM\JoinColumn(name: 'lot_id', referencedColumnName: 'id', nullable: true)]
    private ?Lot $lot = null;

    #[ORM\ManyToOne(targetEntity: Conditionnement::class)]
    #[ORM\JoinColumn(name: 'conditionnement_id', referencedColumnName: 'id', nullable: true)]
    private ?Conditionnement $conditionnement = null;

    #[ORM\ManyToOne(targetEntity: EmplacementStock::class)]
    #[ORM\JoinColumn(name: 'emplacement_source_id', referencedColumnName: 'id', nullable: true)]
    private ?EmplacementStock $emplacementSource = null;

    #[ORM\ManyToOne(targetEntity: EmplacementStock::class)]
    #[ORM\JoinColumn(name: 'emplacement_destination_id', referencedColumnName: 'id', nullable: true)]
    private ?EmplacementStock $emplacementDestination = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3)]
    private ?string $quantite = '0.000';

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $referenceType = null;

    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])]
    private ?int $referenceId = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    public function __construct()
    {
        $this->dateMouvement = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getDateMouvement(): ?\DateTimeInterface { return $this->dateMouvement; }
    public function setDateMouvement(?\DateTimeInterface $d): self { $this->dateMouvement = $d; return $this; }
    public function getTypeMouvement(): string { return $this->typeMouvement; }
    public function setTypeMouvement(string $t): self { $this->typeMouvement = $t; return $this; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $p): self { $this->produit = $p; return $this; }
    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(?Lot $l): self { $this->lot = $l; return $this; }
    public function getConditionnement(): ?Conditionnement { return $this->conditionnement; }
    public function setConditionnement(?Conditionnement $c): self { $this->conditionnement = $c; return $this; }
    public function getEmplacementSource(): ?EmplacementStock { return $this->emplacementSource; }
    public function setEmplacementSource(?EmplacementStock $e): self { $this->emplacementSource = $e; return $this; }
    public function getEmplacementDestination(): ?EmplacementStock { return $this->emplacementDestination; }
    public function setEmplacementDestination(?EmplacementStock $e): self { $this->emplacementDestination = $e; return $this; }
    public function getQuantite(): ?string { return $this->quantite; }
    public function setQuantite(string $q): self { $this->quantite = $q; return $this; }
    public function getReferenceType(): ?string { return $this->referenceType; }
    public function setReferenceType(?string $r): self { $this->referenceType = $r; return $this; }
    public function getReferenceId(): ?int { return $this->referenceId; }
    public function setReferenceId(?int $r): self { $this->referenceId = $r; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $o): self { $this->observation = $o; return $this; }
}