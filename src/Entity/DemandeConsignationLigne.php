<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'demande_consignation_ligne')]
class DemandeConsignationLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DemandeConsignation::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'demande_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?DemandeConsignation $demande = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(name: 'produit_id', referencedColumnName: 'id', nullable: false)]
    private ?Produit $produit = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3)]
    private ?string $quantiteDemandee = '0.000';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3, options: ['default' => '0.000'])]
    private ?string $quantiteAccordee = '0.000';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3, options: ['default' => '0.000'])]
    private ?string $quantiteVendue = '0.000';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3, options: ['default' => '0.000'])]
    private ?string $quantiteRetournee = '0.000';

    public function getId(): ?int { return $this->id; }
    public function getDemande(): ?DemandeConsignation { return $this->demande; }
    public function setDemande(?DemandeConsignation $d): self { $this->demande = $d; return $this; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $p): self { $this->produit = $p; return $this; }
    public function getQuantiteDemandee(): ?string { return $this->quantiteDemandee; }
    public function setQuantiteDemandee(string $q): self { $this->quantiteDemandee = $q; return $this; }
    public function getQuantiteAccordee(): ?string { return $this->quantiteAccordee; }
    public function setQuantiteAccordee(string $q): self { $this->quantiteAccordee = $q; return $this; }
    public function getQuantiteVendue(): ?string { return $this->quantiteVendue; }
    public function setQuantiteVendue(string $q): self { $this->quantiteVendue = $q; return $this; }
    public function getQuantiteRetournee(): ?string { return $this->quantiteRetournee; }
    public function setQuantiteRetournee(string $q): self { $this->quantiteRetournee = $q; return $this; }
}
