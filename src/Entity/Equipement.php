<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Column(type: 'string', length: 50, nullable: true)]
private $rfidTag;

#[ORM\Column(type: 'float', nullable: true)]
private $poidsReference;

#[ORM\Column(type: 'float', nullable: true)]
private $poidsActuel;

#[ORM\Column(type: 'integer', nullable: true)]
private $distanceReference;

#[ORM\Column(type: 'integer', nullable: true)]
private $distanceActuelle;

#[ORM\Column(type: 'datetime', nullable: true)]
private $derniereMiseAJour;

#[ORM\Column(type: 'json', nullable: true)]
private $donneesCapteursHistorique = [];
#[ORM\Entity()]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $nom;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(type: 'string', length: 100)]
    private $etat;

    #[ORM\Column(type: 'boolean')]
    private $disponible = true;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $capteurs;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getNom(): ?string
    {
        return $this->nom;
    }
    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
    public function getEtat(): ?string
    {
        return $this->etat;
    }
    public function setEtat(string $etat): self
    {
        $this->etat = $etat;
        return $this;
    }
    public function isDisponible(): bool
    {
        return $this->disponible;
    }
    public function setDisponible(bool $disponible): self
    {
        $this->disponible = $disponible;
        return $this;
    }
    public function getCapteurs(): ?string
    {
        return $this->capteurs;
    }
    public function setCapteurs(?string $capteurs): self
    {
        $this->capteurs = $capteurs;
        return $this;
    }
}

public function getRfidTag(): ?string
{
    return $this->rfidTag;
}

public function setRfidTag(?string $rfidTag): self
{
    $this->rfidTag = $rfidTag;
    return $this;
}

public function getPoidsReference(): ?float
{
    return $this->poidsReference;
}

public function setPoidsReference(?float $poidsReference): self
{
    $this->poidsReference = $poidsReference;
    return $this;
}

public function getPoidsActuel(): ?float
{
    return $this->poidsActuel;
}

public function setPoidsActuel(?float $poidsActuel): self
{
    $this->poidsActuel = $poidsActuel;
    return $this;
}

public function getDistanceReference(): ?int
{
    return $this->distanceReference;
}

public function setDistanceReference(?int $distanceReference): self
{
    $this->distanceReference = $distanceReference;
    return $this;
}

public function getDistanceActuelle(): ?int
{
    return $this->distanceActuelle;
}

public function setDistanceActuelle(?int $distanceActuelle): self
{
    $this->distanceActuelle = $distanceActuelle;
    return $this;
}

public function getDerniereMiseAJour(): ?\DateTimeInterface
{
    return $this->derniereMiseAJour;
}

public function setDerniereMiseAJour(?\DateTimeInterface $derniereMiseAJour): self
{
    $this->derniereMiseAJour = $derniereMiseAJour;
    return $this;
}

public function getDonneesCapteursHistorique(): array
{
    return $this->donneesCapteursHistorique ?? [];
}

public function setDonneesCapteursHistorique(?array $donneesCapteursHistorique): self
{
    $this->donneesCapteursHistorique = $donneesCapteursHistorique;
    return $this;
}

public function ajouterDonneesHistorique(array $donnees): self
{
    $historique = $this->getDonneesCapteursHistorique();
    $historique[] = array_merge($donnees, ['timestamp' => (new \DateTime())->format('Y-m-d H:i:s')]);
    
    // Garder seulement les 100 dernières entrées
    if (count($historique) > 100) {
        $historique = array_slice($historique, -100);
    }
    
    $this->setDonneesCapteursHistorique($historique);
    return $this;
}

/**
 * Détermine si l'équipement est physiquement présent basé sur les capteurs
 */
public function isPhysiquementPresent(): bool
{
    $tolerance = 0.1; // 10% de tolérance
    $toleranceDistance = 5; // 5cm de tolérance
    
    $poidsOk = true;
    $distanceOk = true;
    
    // Vérification du poids si défini
    if ($this->poidsReference !== null && $this->poidsActuel !== null) {
        $ecartPoids = abs($this->poidsActuel - $this->poidsReference) / $this->poidsReference;
        $poidsOk = $ecartPoids <= $tolerance;
    }
    
    // Vérification de la distance si définie
    if ($this->distanceReference !== null && $this->distanceActuelle !== null) {
        $ecartDistance = abs($this->distanceActuelle - $this->distanceReference);
        $distanceOk = $ecartDistance <= $toleranceDistance;
    }
    
    return $poidsOk && $distanceOk;
}

