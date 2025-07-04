<?php
namespace App\Controller;

use App\Entity\Equipement;
use App\Entity\Reservation;
use App\Repository\EquipementRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReservationController extends AbstractController
{
    #[Route('/reservation', name: 'reservation_index')]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function index(EquipementRepository $equipementRepository, ReservationRepository $reservationRepository, Request $request): Response
    {
        $equipements = $equipementRepository->findAll();
        $user = $this->getUser();

        // Associer la réservation active à chaque équipement
        $reservationsActives = [];
        foreach ($equipements as $equipement) {
            $reservationsActives[$equipement->getId()] = $equipementRepository->findActiveReservation($equipement);
        }

        // Gestion de la réservation pour un tiers
        if ($request->isMethod('POST') && $request->request->get('equipement_id')) {
            // On récupère les infos de la personne pour qui on réserve
            $nom = $request->request->get('nom');
            $postnom = $request->request->get('postnom');
            $prenom = $request->request->get('prenom');
            $promotion = $request->request->get('promotion');
            if (is_array($promotion)) {
                $promotion = $promotion[0]; // on prend la première valeur si jamais un tableau est reçu
            }
            $filiere = $request->request->get('filiere');
            $email = $request->request->get('email');
            $telephone = $request->request->get('telephone');
            $equipement = $equipementRepository->find($request->request->get('equipement_id'));
            if ($equipement && $equipement->isDisponible()) {
                $reservation = new Reservation();
                $reservation->setNomPersonne($nom);
                $reservation->setPostnomPersonne($postnom);
                $reservation->setPrenomPersonne($prenom);
                $reservation->setPromotion($promotion); // string, plus d'erreur non-scalar
                $reservation->setFiliere($filiere);
                $reservation->setEmailPersonne($email);
                $reservation->setTelephone($telephone);
                $reservation->setUser($user); // gestionnaire qui effectue la réservation
                $reservation->setEquipement($equipement);
                $reservation->setDateReservation(new \DateTime());
                $reservation->setActive(true);
                $reservationRepository->getEntityManager()->persist($reservation);
                // Rendre l'équipement indisponible
                $equipement->setDisponible(false);
                $reservationRepository->getEntityManager()->flush();
                $this->addFlash('success', 'Réservation enregistrée pour ' . $prenom . ' ' . $nom . ' !');
                return $this->redirectToRoute('reservation_index');
            } else {
                $this->addFlash('error', "Cet équipement n'est plus disponible.");
            }
        }

        return $this->render('reservation/index.html.twig', [
            'equipements' => $equipements,
            'reservationsActives' => $reservationsActives
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
