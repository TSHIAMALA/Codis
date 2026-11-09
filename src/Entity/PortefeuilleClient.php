<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'portefeuille_client')]
class PortefeuilleClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AgentCommercial::class)]
    #[ORM\JoinColumn(name: 'agent_id', referencedColumnName: 'id', nullable: false)]
    private ?AgentCommercial $agent = null;

    #[ORM\ManyToOne(targetEntity: Detailant::class)]
    #[ORM\JoinColumn(name: 'detailant_id', referencedColumnName: 'id', nullable: false)]
    private ?Detailant $detailant = null;

    #[ORM\Column(type: 'date')] // initialisée dans le constructeur
    private ?\DateTimeInterface $dateAttribution = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    public function __construct()
    {
        $this->dateAttribution = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAgent(): ?AgentCommercial { return $this->agent; }
    public function setAgent(?AgentCommercial $a): self { $this->agent = $a; return $this; }
    public function getDetailant(): ?Detailant { return $this->detailant; }
    public function setDetailant(?Detailant $d): self { $this->detailant = $d; return $this; }
    public function getDateAttribution(): ?\DateTimeInterface { return $this->dateAttribution; }
    public function setDateAttribution(?\DateTimeInterface $d): self { $this->dateAttribution = $d; return $this; }
    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $d): self { $this->dateFin = $d; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $a): self { $this->actif = $a; return $this; }
}