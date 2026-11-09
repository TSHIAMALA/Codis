<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'lot')]
#[ORM\UniqueConstraint(name: 'numero_lot', columns: ['numero_lot'])]
class Lot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(name: 'produit_id', referencedColumnName: 'id', nullable: false)]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(targetEntity: Usine::class)]
    #[ORM\JoinColumn(name: 'usine_id', referencedColumnName: 'id', nullable: true)]
    private ?Usine $usine = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $numeroLot = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateFabrication = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'ACTIF'])]
    private string $statut = 'ACTIF'; // ACTIF, BLOQUE, EPUISE

    public function getId(): ?int { return $this->id; }
    public function getProduit(): ?Produit { return $this->produit; }
    public function setProduit(?Produit $p): self { $this->produit = $p; return $this; }
    public function getUsine(): ?Usine { return $this->usine; }
    public function setUsine(?Usine $u): self { $this->usine = $u; return $this; }
    public function getNumeroLot(): ?string { return $this->numeroLot; }
    public function setNumeroLot(string $n): self { $this->numeroLot = $n; return $this; }
    public function getDateFabrication(): ?\DateTimeInterface { return $this->dateFabrication; }
    public function setDateFabrication(?\DateTimeInterface $d): self { $this->dateFabrication = $d; return $this; }
    public function getDateExpiration(): ?\DateTimeInterface { return $this->dateExpiration; }
    public function setDateExpiration(?\DateTimeInterface $d): self { $this->dateExpiration = $d; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }
}