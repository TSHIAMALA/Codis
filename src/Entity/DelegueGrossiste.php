<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'delegue_grossiste')]
#[ORM\UniqueConstraint(name: 'uk_delegue_grossiste', columns: ['delegue_id', 'grossiste_id'])]
class DelegueGrossiste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Delegue::class)]
    #[ORM\JoinColumn(name: 'delegue_id', referencedColumnName: 'id', nullable: false)]
    private ?Delegue $delegue = null;

    #[ORM\ManyToOne(targetEntity: Grossiste::class)]
    #[ORM\JoinColumn(name: 'grossiste_id', referencedColumnName: 'id', nullable: false)]
    private ?Grossiste $grossiste = null;

    #[ORM\Column(type: 'date')] // initialisée dans le constructeur
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    public function __construct()
    {
        $this->dateDebut = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getDelegue(): ?Delegue { return $this->delegue; }
    public function setDelegue(?Delegue $d): self { $this->delegue = $d; return $this; }
    public function getGrossiste(): ?Grossiste { return $this->grossiste; }
    public function setGrossiste(?Grossiste $g): self { $this->grossiste = $g; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $d): self { $this->dateDebut = $d; return $this; }
    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $d): self { $this->dateFin = $d; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $a): self { $this->actif = $a; return $this; }
}