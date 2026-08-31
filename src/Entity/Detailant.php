<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'detailant')]
class Detailant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Pays::class)]
    #[ORM\JoinColumn(name: 'pays_id', referencedColumnName: 'id', nullable: false)]
    private ?Pays $pays = null;

    #[ORM\ManyToOne(targetEntity: Region::class)]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: true)]
    private ?Region $region = null;

    #[ORM\ManyToOne(targetEntity: Ville::class)]
    #[ORM\JoinColumn(name: 'ville_id', referencedColumnName: 'id', nullable: true)]
    private ?Ville $ville = null;

    #[ORM\ManyToOne(targetEntity: ZoneCommerciale::class)]
    #[ORM\JoinColumn(name: 'zone_id', referencedColumnName: 'id', nullable: true)]
    private ?ZoneCommerciale $zone = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 200)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $nomResponsable = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'ACTIF'])]
    private string $statut = 'ACTIF'; // EN_ATTENTE, ACTIF, SUSPENDU, INACTIF

    public function getId(): ?int { return $this->id; }
    public function getPays(): ?Pays { return $this->pays; }
    public function setPays(?Pays $p): self { $this->pays = $p; return $this; }
    public function getRegion(): ?Region { return $this->region; }
    public function setRegion(?Region $r): self { $this->region = $r; return $this; }
    public function getVille(): ?Ville { return $this->ville; }
    public function setVille(?Ville $v): self { $this->ville = $v; return $this; }
    public function getZone(): ?ZoneCommerciale { return $this->zone; }
    public function setZone(?ZoneCommerciale $z): self { $this->zone = $z; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $c): self { $this->code = $c; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $n): self { $this->nom = $n; return $this; }
    public function getNomResponsable(): ?string { return $this->nomResponsable; }
    public function setNomResponsable(?string $n): self { $this->nomResponsable = $n; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $t): self { $this->telephone = $t; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $e): self { $this->email = $e; return $this; }
    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $a): self { $this->adresse = $a; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }
}