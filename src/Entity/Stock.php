<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stock')]
class Stock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EmplacementStock::class)]
    #[ORM\JoinColumn(name: 'emplacement_id', referencedColumnName: 'id', nullable: false)]
    private ?EmplacementStock $emplacement = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(name: 'produit_id', referencedColumnName: 'id', nullable: false)]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(targetEntity: Lot::class)]
    #[ORM\JoinColumn(name: 'lot_id', referencedColumnName: 'id', nullable: true)]
    private ?Lot $lot = null;

    #[ORM\ManyToOne(targetEntity: Conditionnement::class)]
    #[ORM\JoinColumn(name: 'conditionnement_id', referencedColumnName: 'id', nullable: true)]
    private ?Conditionnement $conditionnement = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3, options: ['default' => '0.000'])]
    private ?string $quantiteDisponible = '0.000';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3, options: ['default' => '0.000'])]
    private ?string $quantiteReservee = '0.000';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3, options: ['default' => '0.000'])]
    private ?string $quantiteTransit = '0.000';

    #[ORM\Column(type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmplacement(): ?EmplacementStock { return $this->emplacement; }
    public function setEmplacement(?EmplacementStock $e): self { $this->emplacement = $e; return $this; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $p): self { $this->produit = $p; return $this; }
    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(?Lot $l): self { $this->lot = $l; return $this; }
    public function getConditionnement(): ?Conditionnement { return $this->conditionnement; }
    public function setConditionnement(?Conditionnement $c): self { $this->conditionnement = $c; return $this; }
    public function getQuantiteDisponible(): ?string { return $this->quantiteDisponible; }
    public function setQuantiteDisponible(string $q): self { $this->quantiteDisponible = $q; return $this; }
    public function getQuantiteReservee(): ?string { return $this->quantiteReservee; }
    public function setQuantiteReservee(string $q): self { $this->quantiteReservee = $q; return $this; }
    public function getQuantiteTransit(): ?string { return $this->quantiteTransit; }
    public function setQuantiteTransit(string $q): self { $this->quantiteTransit = $q; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $u): self { $this->updatedAt = $u; return $this; }
}