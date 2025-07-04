<?php
namespace App\Controller;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reservations')]
class AdminReservationController extends AbstractController
{
    #[Route('/', name: 'admin_reservation_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $reservations = $em->getRepository(Reservation::class)->findBy([], ['dateReservation' => 'DESC']);
        // Ajout des liens de navigation admin
        $adminMenus = [
            [ 'label' => 'Utilisateurs', 'route' => 'admin_user_index' ],
            [ 'label' => 'Équipements', 'route' => 'admin_equipement_index' ],
            [ 'label' => 'Réservations', 'route' => 'admin_reservation_index' ],
        ];
        return $this->render('admin/reservation/index.html.twig', [
            'reservations' => $reservations,
            'adminMenus' => $adminMenus
        ]);
    }
}
