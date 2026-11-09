<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'livraison')]
#[ORM\Index(name: 'idx_livraison_statut', columns: ['statut'])]
class Livraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private ?string $numero = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'livraisons')]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'id', nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(targetEntity: Depot::class)]
    #[ORM\JoinColumn(name: 'depot_id', referencedColumnName: 'id', nullable: true)]
    private ?Depot $depot = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $datePrevue = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateDepart = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateLivraison = null;

    #[ORM\Column(type: 'string', length: 30, options: ['default' => 'PLANIFIEE'])]
    private string $statut = 'PLANIFIEE'; // PLANIFIEE, PREPARATION, EXPEDIEE, EN_TRANSIT, LIVREE, RECEPTION_CONFIRMEE, INCIDENT

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\OneToMany(mappedBy: 'livraison', targetEntity: LivraisonLigne::class, cascade: ['persist', 'remove'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->datePrevue = new \DateTime('+1 day');
    }

    public function getId(): ?int { return $this->id; }
    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $n): self { $this->numero = $n; return $this; }
    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $c): self { $this->commande = $c; return $this; }
    public function getDepot(): ?Depot { return $this->depot; }
    public function setDepot(?Depot $d): self { $this->depot = $d; return $this; }
    public function getDatePrevue(): ?\DateTimeInterface { return $this->datePrevue; }
    public function setDatePrevue(?\DateTimeInterface $d): self { $this->datePrevue = $d; return $this; }
    public function getDateDepart(): ?\DateTimeInterface { return $this->dateDepart; }
    public function setDateDepart(?\DateTimeInterface $d): self { $this->dateDepart = $d; return $this; }
    public function getDateLivraison(): ?\DateTimeInterface { return $this->dateLivraison; }
    public function setDateLivraison(?\DateTimeInterface $d): self { $this->dateLivraison = $d; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = $s; return $this; }
    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $o): self { $this->observation = $o; return $this; }
    public function getLignes(): Collection { return $this->lignes; }
}