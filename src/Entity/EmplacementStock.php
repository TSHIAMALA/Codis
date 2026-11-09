<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'emplacement_stock')]
class EmplacementStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $typeEmplacement = 'DEPOT'; // USINE, DEPOT, DR (stock confié à un DR en tournée)

    #[ORM\ManyToOne(targetEntity: AgentCommercial::class)]
    #[ORM\JoinColumn(name: 'agent_id', referencedColumnName: 'id', nullable: true)]
    private ?AgentCommercial $agent = null;

    #[ORM\ManyToOne(targetEntity: Usine::class)]
    #[ORM\JoinColumn(name: 'usine_id', referencedColumnName: 'id', nullable: true)]
    private ?Usine $usine = null;

    #[ORM\ManyToOne(targetEntity: Depot::class)]
    #[ORM\JoinColumn(name: 'depot_id', referencedColumnName: 'id', nullable: true)]
    private ?Depot $depot = null;

    #[ORM\Column(type: 'string', length: 150)]
    private ?string $nom = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    public function getId(): ?int { return $this->id; }
    public function getTypeEmplacement(): string { return $this->typeEmplacement; }
    public function setTypeEmplacement(string $t): self { $this->typeEmplacement = $t; return $this; }
    public function getAgent(): ?AgentCommercial { return $this->agent; }
    public function setAgent(?AgentCommercial $a): self { $this->agent = $a; return $this; }
    public function getUsine(): ?Usine { return $this->usine; }
    public function setUsine(?Usine $u): self { $this->usine = $u; return $this; }
    public function getDepot(): ?Depot { return $this->depot; }
    public function setDepot(?Depot $d): self { $this->depot = $d; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): self { $this->actif = $actif; return $this; }
}