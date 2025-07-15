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
    private  $id;

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

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $nomPersonne;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $prenomPersonne;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $emailPersonne;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $postnomPersonne;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $promotion = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $filiere;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $telephone;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $dateRemise;

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getEquipement(): ?Equipement { return $this->equipement; }
    public function setEquipement(Equipement $equipement): self { $this->equipement = $equipement; return $this; }
    public function getDateReservation(): ?\DateTimeInterface { return $this->dateReservation; }
    public function setDateReservation(\DateTimeInterface $date): self { $this->dateReservation = $date; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
    public function getNomPersonne(): ?string { return $this->nomPersonne; }
    public function setNomPersonne(?string $nom): self { $this->nomPersonne = $nom; return $this; }
    public function getPrenomPersonne(): ?string { return $this->prenomPersonne; }
    public function setPrenomPersonne(?string $prenom): self { $this->prenomPersonne = $prenom; return $this; }
    public function getEmailPersonne(): ?string { return $this->emailPersonne; }
    public function setEmailPersonne(?string $email): self { $this->emailPersonne = $email; return $this; }
    public function getPostnomPersonne(): ?string { return $this->postnomPersonne; }
    public function setPostnomPersonne(?string $postnom): self { $this->postnomPersonne = $postnom; return $this; }
    public function getPromotion(): ?string { return $this->promotion; }
    public function setPromotion(?string $promotion): self { $this->promotion = $promotion; return $this; }
    public function getFiliere(): ?string { return $this->filiere; }
    public function setFiliere(?string $filiere): self { $this->filiere = $filiere; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): self { $this->telephone = $telephone; return $this; }
    public function getDateRemise(): ?\DateTimeInterface
    {
        return $this->dateRemise;
    }
    public function setDateRemise(?\DateTimeInterface $dateRemise): self
    {
        $this->dateRemise = $dateRemise;
        return $this;
    }
}
