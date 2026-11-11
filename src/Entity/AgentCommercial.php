<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'agent_commercial')]
class AgentCommercial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: false, unique: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Pays::class)]
    #[ORM\JoinColumn(name: 'pays_id', referencedColumnName: 'id', nullable: false)]
    private ?Pays $pays = null;

    #[ORM\ManyToOne(targetEntity: Region::class)]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: true)]
    private ?Region $region = null;

    #[ORM\ManyToOne(targetEntity: ZoneCommerciale::class)]
    #[ORM\JoinColumn(name: 'zone_id', referencedColumnName: 'id', nullable: true)]
    private ?ZoneCommerciale $zone = null;

    #[ORM\ManyToOne(targetEntity: Delegue::class)]
    #[ORM\JoinColumn(name: 'delegue_id', referencedColumnName: 'id', nullable: true)]
    private ?Delegue $delegue = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'DR'])]
    private string $typeAgent = 'DR'; // 'ASM', 'SUPERVISEUR', 'DR', 'CYCLO'

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'superviseur_id', referencedColumnName: 'id', nullable: true)]
    private ?self $superviseur = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getPays(): ?Pays { return $this->pays; }
    public function setPays(?Pays $p): self { $this->pays = $p; return $this; }
    public function getRegion(): ?Region { return $this->region; }
    public function setRegion(?Region $r): self { $this->region = $r; return $this; }
    public function getZone(): ?ZoneCommerciale { return $this->zone; }
    public function setZone(?ZoneCommerciale $z): self { $this->zone = $z; return $this; }
    public function getDelegue(): ?Delegue { return $this->delegue; }
    public function setDelegue(?Delegue $d): self { $this->delegue = $d; return $this; }
    public function getTypeAgent(): string { return $this->typeAgent; }
    public function setTypeAgent(string $t): self { $this->typeAgent = $t; return $this; }
    public function getSuperviseur(): ?self { return $this->superviseur; }
    public function setSuperviseur(?self $s): self { $this->superviseur = $s; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $a): self { $this->actif = $a; return $this; }

    /** Fonctions commerciales, du haut au bas de la hiérarchie directe. */
    public const TYPES = ['ASM', 'SUPERVISEUR', 'DR', 'CYCLO'];

    /** Fonction attendue du supérieur direct (null : l'ASM n'a pas de supérieur dans le réseau). */
    public static function typeSuperieur(string $typeAgent): ?string
    {
        return match ($typeAgent) {
            'SUPERVISEUR' => 'ASM',
            'DR' => 'SUPERVISEUR',
            'CYCLO' => 'DR',
            default => null,
        };
    }

    public function getTypeAgentLibelle(): string
    {
        return match($this->typeAgent) {
            'ASM' => 'ASM (Area Sales Manager)',
            'SUPERVISEUR' => 'Superviseur des Ventes',
            'DR' => 'DR (Distribution Representative)',
            'CYCLO' => 'Cyclo (Vendeur Ambulant)',
            default => $this->typeAgent,
        };
    }
}