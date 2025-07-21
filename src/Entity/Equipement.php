<?php

namespace App\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: \App\Repository\EquipementRepository::class)]
class Equipement
{
    // États possibles de l’équipement
    public const ETAT_DISPONIBLE = 'Disponible';
    public const ETAT_PRIS = 'Pris';
    public const ETAT_MAINTENANCE = 'Maintenance';

    public const ETAT_DECLASSER = 'Declasser';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $nom;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(type: 'string', length: 100, options: ['default' => self::ETAT_DISPONIBLE])]
    private $etat = self::ETAT_DISPONIBLE;


    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $capteurs;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private $maintenancier;

    #[ORM\Column(type:'string', length: 50, nullable: true)]
    private ?string $code = null;
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $rfidTag;

    #[ORM\Column(type: 'float', nullable: true)]
    private $poidsActuel;

    #[ORM\Column(type: 'float', nullable: true)]
    private $poidsReference;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $distanceReference;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $distanceActuelle;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $distance1;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $distance2;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $derniereMiseAJour;

    #[ORM\Column(type: 'json', nullable: true)]
    private $donneesCapteursHistorique = [];

    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'equipement')]
    private $reservations;

    public function __construct()
    {
        $this->etat = self::ETAT_DISPONIBLE;
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getEtat(): ?string { return $this->etat; }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;
        return $this;
    }

    public function getCapteurs(): ?string { return $this->capteurs; }

    public function setCapteurs(?string $capteurs): self
    {
        $this->capteurs = $capteurs;
        return $this;
    }

    public function getMaintenancier(): ?User { return $this->maintenancier; }

    public function setMaintenancier(?User $maintenancier): self
    {
        $this->maintenancier = $maintenancier;
        return $this;
    }

    public function getRfidTag(): ?string { return $this->rfidTag; }

    public function setRfidTag(?string $rfidTag): self {
        $this->rfidTag = $rfidTag;
        return $this;
    }

    public function getPoidsActuel(): ?float { return $this->poidsActuel; }

    public function setPoidsActuel(?float $poidsActuel): self {
        $this->poidsActuel = $poidsActuel;
        return $this;
    }

    public function getPoidsReference(): ?float { return $this->poidsReference; }
    public function setPoidsReference(?float $poidsReference): self {
        $this->poidsReference = $poidsReference;
        return $this;
    }

    public function getDistanceReference(): ?int { return $this->distanceReference; }

    public function setDistanceReference(?int $distanceReference): self
    {
        $this->distanceReference = $distanceReference;
        return $this;
    }

    public function getDistanceActuelle(): ?int { return $this->distanceActuelle; }

    public function setDistanceActuelle(?int $distanceActuelle): self
    {
        $this->distanceActuelle = $distanceActuelle;
        return $this;
    }

    public function getDerniereMiseAJour(): ?\DateTimeInterface { return $this->derniereMiseAJour; }

    public function setDerniereMiseAJour(?\DateTimeInterface $date): self
    {
        $this->derniereMiseAJour = $date;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }


    public function getDonneesCapteursHistorique(): array
    {
        return $this->donneesCapteursHistorique ?? [];
    }

    public function setDonneesCapteursHistorique(?array $historique): self
    {
        $this->donneesCapteursHistorique = $historique;
        return $this;
    }

    public function ajouterDonneesHistorique(array $donnees): self
    {
        $historique = $this->getDonneesCapteursHistorique();
        $historique[] = array_merge($donnees, ['timestamp' => (new \DateTime())->format('Y-m-d H:i:s')]);

        if (count($historique) > 100) {
            $historique = array_slice($historique, -100);
        }

        return $this->setDonneesCapteursHistorique($historique);
    }

    public function isPhysiquementPresent(): bool
    {
        $tolerancePoids = 0.1;
        $toleranceDistance = 5;

        $poidsOk = true;
        $distanceOk = true;

        if ($this->poidsReference !== null && $this->poidsActuel !== null) {
            $ecart = abs($this->poidsActuel - $this->poidsReference) / $this->poidsReference;
            $poidsOk = $ecart <= $tolerancePoids;
        }

        if ($this->distanceReference !== null && $this->distanceActuelle !== null) {
            $ecart = abs($this->distanceActuelle - $this->distanceReference);
            $distanceOk = $ecart <= $toleranceDistance;
        }

        return $poidsOk && $distanceOk;
    }

    public function getDistance1(): ?int { return $this->distance1; }
    public function setDistance1(?int $distance1): self {
        $this->distance1 = $distance1;
        return $this;
    }
    public function getDistance2(): ?int { return $this->distance2; }
    public function setDistance2(?int $distance2): self {
        $this->distance2 = $distance2;
        return $this;
    }

    /**
     * @return Collection|Reservation[]
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }
}
