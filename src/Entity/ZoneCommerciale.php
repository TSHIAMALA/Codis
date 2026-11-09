<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'zone_commerciale')]
#[ORM\UniqueConstraint(name: 'uk_zone_ville_code', columns: ['ville_id', 'code'])]
class ZoneCommerciale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ville::class, inversedBy: 'zones')]
    #[ORM\JoinColumn(name: 'ville_id', referencedColumnName: 'id', nullable: false)]
    private ?Ville $ville = null;

    #[ORM\Column(type: 'string', length: 30)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 150)]
    private ?string $nom = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    public function getId(): ?int { return $this->id; }
    public function getVille(): ?Ville { return $this->ville; }
    public function setVille(?Ville $ville): self { $this->ville = $ville; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): self { $this->actif = $actif; return $this; }
}