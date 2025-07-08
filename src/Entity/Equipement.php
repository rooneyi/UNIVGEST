<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

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

    #[ORM\ManyToOne(targetEntity: User::class)]
    private $maintenancier;

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
    public function getMaintenancier(): ?User
    {
        return $this->maintenancier;
    }
    public function setMaintenancier(?User $maintenancier): self
    {
        $this->maintenancier = $maintenancier;
        return $this;
    }
}
