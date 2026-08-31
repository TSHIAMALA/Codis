<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'livraison_ligne')]
class LivraisonLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Livraison::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'livraison_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Livraison $livraison = null;

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
    private ?string $quantitePrevue = '0.000';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3, nullable: true)]
    private ?string $quantiteLivree = null;

    public function getId(): ?int { return $this->id; }
    public function getLivraison(): ?Livraison { return $this->livraison; }
    public function setLivraison(?Livraison $l): self { $this->livraison = $l; return $this; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $p): self { $this->produit = $p; return $this; }
    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(?Lot $l): self { $this->lot = $l; return $this; }
    public function getConditionnement(): ?Conditionnement { return $this->conditionnement; }
    public function setConditionnement(?Conditionnement $c): self { $this->conditionnement = $c; return $this; }
    public function getQuantitePrevue(): ?string { return $this->quantitePrevue; }
    public function setQuantitePrevue(string $q): self { $this->quantitePrevue = $q; return $this; }
    public function getQuantiteLivree(): ?string { return $this->quantiteLivree; }
    public function setQuantiteLivree(?string $q): self { $this->quantiteLivree = $q; return $this; }
}