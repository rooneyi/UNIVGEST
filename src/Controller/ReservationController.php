<?php
namespace App\Controller;

use App\Entity\Equipement;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ReservationController extends AbstractController
{
    #[Route('/reservation', name: 'reservation_index')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $equipements = $em->getRepository(Equipement::class)->findAll();
        $user = $this->getUser();

        // Gestion de la réservation
        if ($request->isMethod('POST') && $request->request->get('equipement_id')) {
            if (!$user) {
                throw new AccessDeniedException('Vous devez être connecté pour réserver.');
            }
            $equipement = $em->getRepository(Equipement::class)->find($request->request->get('equipement_id'));
            if ($equipement && $equipement->isDisponible()) {
                // Créer une réservation
                $reservation = new Reservation();
                $reservation->setUser($user);
                $reservation->setEquipement($equipement);
                $reservation->setDateReservation(new \DateTime());
                $reservation->setActive(true);
                $em->persist($reservation);
                // Rendre l'équipement indisponible
                $equipement->setDisponible(false);
                $em->flush();
                $this->addFlash('success', 'Réservation effectuée avec succès !');
                return $this->redirectToRoute('reservation_index');
            } else {
                $this->addFlash('error', "Cet équipement n'est plus disponible.");
            }
        }

        return $this->render('reservation/index.html.twig', [
            'equipements' => $equipements
        ]);
    }

    #[Route('/mes-reservations', name: 'mes_reservations')]
    public function mesReservations(EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Annulation d'une réservation
        if ($request->isMethod('POST') && $request->request->get('reservation_id')) {
            $reservation = $em->getRepository(Reservation::class)->find($request->request->get('reservation_id'));
            if ($reservation && $reservation->getUser() === $user && $reservation->isActive()) {
                $reservation->setActive(false);
                $equipement = $reservation->getEquipement();
                $equipement->setDisponible(true);
                $em->flush();
                $this->addFlash('success', 'Réservation annulée avec succès.');
                return $this->redirectToRoute('mes_reservations');
            } else {
                $this->addFlash('error', 'Impossible d’annuler cette réservation.');
            }
        }

        $reservations = $em->getRepository(Reservation::class)->findBy([
            'user' => $user
        ], [ 'dateReservation' => 'DESC' ]);

        return $this->render('reservation/mes_reservations.html.twig', [
            'reservations' => $reservations
        ]);
    }
}
