<?php
namespace App\Entity;

use App\Entity\User;
use App\Entity\Equipement;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    #[ORM\ManyToOne(targetEntity: Equipement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $equipement;

    #[ORM\Column(type: 'datetime')]
    private $dateReservation;

    #[ORM\Column(type: 'boolean')]
    private $active = true;

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getEquipement(): ?Equipement { return $this->equipement; }
    public function setEquipement(Equipement $equipement): self { $this->equipement = $equipement; return $this; }
    public function getDateReservation(): ?\DateTimeInterface { return $this->dateReservation; }
    public function setDateReservation(\DateTimeInterface $date): self { $this->dateReservation = $date; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
}
