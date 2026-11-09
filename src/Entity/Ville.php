<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'ville')]
class Ville
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Region::class, inversedBy: 'villes')]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: false)]
    private ?Region $region = null;

    #[ORM\Column(type: 'string', length: 150)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 30, options: ['default' => 'VILLE'])]
    private string $typeVille = 'VILLE'; // CHEF_LIEU, VILLE, LOCALITE

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\OneToMany(mappedBy: 'ville', targetEntity: ZoneCommerciale::class)]
    private Collection $zones;

    public function __construct()
    {
        $this->zones = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getRegion(): ?Region { return $this->region; }
    public function setRegion(?Region $region): self { $this->region = $region; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getTypeVille(): string { return $this->typeVille; }
    public function setTypeVille(string $typeVille): self { $this->typeVille = $typeVille; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): self { $this->actif = $actif; return $this; }
    public function getZones(): Collection { return $this->zones; }
}