<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'produit')]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Marque::class)]
    #[ORM\JoinColumn(name: 'marque_id', referencedColumnName: 'id', nullable: false)]
    private ?Marque $marque = null;

    #[ORM\ManyToOne(targetEntity: CategorieProduit::class)]
    #[ORM\JoinColumn(name: 'categorie_id', referencedColumnName: 'id', nullable: false)]
    private ?CategorieProduit $categorie = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 150)]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: Conditionnement::class, cascade: ['persist', 'remove'])]
    private Collection $conditionnements;

    public function __construct()
    {
        $this->conditionnements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getMarque(): ?Marque { return $this->marque; }
    public function setMarque(?Marque $marque): self { $this->marque = $marque; return $this; }
    public function getCategorie(): ?CategorieProduit { return $this->categorie; }
    public function setCategorie(?CategorieProduit $categorie): self { $this->categorie = $categorie; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): self { $this->actif = $actif; return $this; }
    public function getConditionnements(): Collection { return $this->conditionnements; }
}