<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'conditionnement')]
class Conditionnement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Produit::class, inversedBy: 'conditionnements')]
    #[ORM\JoinColumn(name: 'produit_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Produit $produit = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $libelle = null;

    #[ORM\Column(type: 'integer')]
    private ?int $niveau = 1;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $uniteReference = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3)]
    private ?string $quantiteConversion = '1.000';

    public function getId(): ?int { return $this->id; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $produit): self { $this->produit = $produit; return $this; }
    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(string $libelle): self { $this->libelle = $libelle; return $this; }
    public function getNiveau(): ?int { return $this->niveau; }
    public function setNiveau(int $niveau): self { $this->niveau = $niveau; return $this; }
    public function getUniteReference(): ?string { return $this->uniteReference; }
    public function setUniteReference(?string $uniteReference): self { $this->uniteReference = $uniteReference; return $this; }
    public function getQuantiteConversion(): ?string { return $this->quantiteConversion; }
    public function setQuantiteConversion(string $quantiteConversion): self { $this->quantiteConversion = $quantiteConversion; return $this; }
}