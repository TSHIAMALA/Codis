<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'transfert_stock_ligne')]
class TransfertStockLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TransfertStock::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'transfert_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TransfertStock $transfert = null;

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
    private ?string $quantiteEnvoyee = '0.000';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3, nullable: true)]
    private ?string $quantiteRecue = null;

    public function getId(): ?int { return $this->id; }
    public function getTransfert(): ?TransfertStock { return $this->transfert; }
    public function setTransfert(?TransfertStock $t): self { $this->transfert = $t; return $this; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $p): self { $this->produit = $p; return $this; }
    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(?Lot $l): self { $this->lot = $l; return $this; }
    public function getConditionnement(): ?Conditionnement { return $this->conditionnement; }
    public function setConditionnement(?Conditionnement $c): self { $this->conditionnement = $c; return $this; }
    public function getQuantiteEnvoyee(): ?string { return $this->quantiteEnvoyee; }
    public function setQuantiteEnvoyee(string $q): self { $this->quantiteEnvoyee = $q; return $this; }
    public function getQuantiteRecue(): ?string { return $this->quantiteRecue; }
    public function setQuantiteRecue(?string $q): self { $this->quantiteRecue = $q; return $this; }
}