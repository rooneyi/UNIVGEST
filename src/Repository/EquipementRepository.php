<?php

namespace App\Repository;

use App\Entity\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipement::class);
    }

    /**
     * Retourne la réservation active (en cours) pour un équipement donné
     */
    public function findActiveReservation(\App\Entity\Equipement $equipement): ?\App\Entity\Reservation
    {
        return $this->getEntityManager()->getRepository(\App\Entity\Reservation::class)
            ->createQueryBuilder('r')
            ->where('r.equipement = :equipement')
            ->andWhere('r.active = true')
            ->setParameter('equipement', $equipement)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
