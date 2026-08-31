<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'demande_consignation')]
class DemandeConsignation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private ?string $numero = null;

    #[ORM\ManyToOne(targetEntity: AgentCommercial::class)]
    #[ORM\JoinColumn(name: 'dr_id', referencedColumnName: 'id', nullable: false)]
    private ?AgentCommercial $dr = null;

    #[ORM\ManyToOne(targetEntity: Depot::class)]
    #[ORM\JoinColumn(name: 'depot_id', referencedColumnName: 'id', nullable: false)]
    private ?Depot $depot = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateDemande = null;

    #[ORM\Column(type: 'string', length: 30, options: ['default' => 'SOUMISE'])]
    private string $statut = 'SOUMISE'; // SOUMISE, VALIDEE_SUPERVISEUR, VALIDEE_ASM, CLOTUREE, REJETEE

    #[ORM\ManyToOne(targetEntity: AgentCommercial::class)]
    #[ORM\JoinColumn(name: 'superviseur_id', referencedColumnName: 'id', nullable: true)]
    private ?AgentCommercial $superviseur = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateValidationSuperviseur = null;

    #[ORM\ManyToOne(targetEntity: AgentCommercial::class)]
    #[ORM\JoinColumn(name: 'asm_id', referencedColumnName: 'id', nullable: true)]
    private ?AgentCommercial $asm = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateValidationAsm = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, options: ['default' => '0.00'])]
    private ?string $cashJustifie = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\OneToMany(mappedBy: 'demande', targetEntity: DemandeConsignationLigne::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->dateDemande = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $n): self { $this->numero = $n; return $this; }
    public function getDr(): ?AgentCommercial { return $this->dr; }
    public function setDr(?AgentCommercial $dr): self { $this->dr = $dr; return $this; }
    public function getDepot(): ?Depot { return $this->depot; }
    public function setDepot(?Depot $d): self { $this->depot = $d; return $this; }
    public function getDateDemande(): ?\DateTimeInterface { return $this->dateDemande; }
    public function setDateDemande(\DateTimeInterface $d): self { $this->dateDemande = $d; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }
    public function getSuperviseur(): ?AgentCommercial { return $this->superviseur; }
    public function setSuperviseur(?AgentCommercial $s): self { $this->superviseur = $s; return $this; }
    public function getDateValidationSuperviseur(): ?\DateTimeInterface { return $this->dateValidationSuperviseur; }
    public function setDateValidationSuperviseur(?\DateTimeInterface $d): self { $this->dateValidationSuperviseur = $d; return $this; }
    public function getAsm(): ?AgentCommercial { return $this->asm; }
    public function setAsm(?AgentCommercial $a): self { $this->asm = $a; return $this; }
    public function getDateValidationAsm(): ?\DateTimeInterface { return $this->dateValidationAsm; }
    public function setDateValidationAsm(?\DateTimeInterface $d): self { $this->dateValidationAsm = $d; return $this; }
    public function getCashJustifie(): ?string { return $this->cashJustifie; }
    public function setCashJustifie(string $c): self { $this->cashJustifie = $c; return $this; }
    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $o): self { $this->observation = $o; return $this; }

    public function getLignes(): Collection { return $this->lignes; }
    public function addLigne(DemandeConsignationLigne $ligne): self
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes[] = $ligne;
            $ligne->setDemande($this);
        }
        return $this;
    }
    public function removeLigne(DemandeConsignationLigne $ligne): self
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getDemande() === $this) {
                $ligne->setDemande(null);
            }
        }
        return $this;
    }
}
