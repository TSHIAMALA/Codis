<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'production_ligne')]
class ProductionLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Production::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'production_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Production $production = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(name: 'produit_id', referencedColumnName: 'id', nullable: false)]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(targetEntity: Lot::class)]
    #[ORM\JoinColumn(name: 'lot_id', referencedColumnName: 'id', nullable: true)]
    private ?Lot $lot = null;

    #[ORM\ManyToOne(targetEntity: Conditionnement::class)]
    #[ORM\JoinColumn(name: 'conditionnement_id', referencedColumnName: 'id', nullable: true)]
    private ?Conditionnement $conditionnement = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3)]
    private ?string $quantite = '0.000';

    public function getId(): ?int { return $this->id; }
    public function getProduction(): ?Production { return $this->production; }
    public function setProduction(?Production $p): self { $this->production = $p; return $this; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $p): self { $this->produit = $p; return $this; }
    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(?Lot $l): self { $this->lot = $l; return $this; }
    public function getConditionnement(): ?Conditionnement { return $this->conditionnement; }
    public function setConditionnement(?Conditionnement $c): self { $this->conditionnement = $c; return $this; }
    public function getQuantite(): ?string { return $this->quantite; }
    public function setQuantite(string $q): self { $this->quantite = $q; return $this; }
}