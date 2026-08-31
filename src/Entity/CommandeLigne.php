<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'commande_ligne')]
class CommandeLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(name: 'produit_id', referencedColumnName: 'id', nullable: false)]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(targetEntity: Conditionnement::class)]
    #[ORM\JoinColumn(name: 'conditionnement_id', referencedColumnName: 'id', nullable: false)]
    private ?Conditionnement $conditionnement = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 3)]
    private ?string $quantite = '0.000';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $prixUnitaire = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $montant = '0.00';

    public function getId(): ?int { return $this->id; }
    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $c): self { $this->commande = $c; return $this; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $p): self { $this->produit = $p; return $this; }
    public function getConditionnement(): ?Conditionnement { return $this->conditionnement; }
    public function setConditionnement(?Conditionnement $c): self { $this->conditionnement = $c; return $this; }
    public function getQuantite(): ?string { return $this->quantite; }
    public function setQuantite(string $q): self { $this->quantite = $q; return $this; }
    public function getPrixUnitaire(): ?string { return $this->prixUnitaire; }
    public function setPrixUnitaire(string $p): self { $this->prixUnitaire = $p; return $this; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $m): self { $this->montant = $m; return $this; }
}