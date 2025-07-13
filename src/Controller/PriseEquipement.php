<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Equipement;
use App\Repository\EquipementRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PriseEquipement extends AbstractController
{
    #[Route('/pris', name: 'reservation_index')]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function index(
        EquipementRepository $equipementRepository,
        ReservationRepository $reservationRepository,
        Request $request
    ): Response {
        $equipements = $equipementRepository->findAll();
        $user = $this->getUser();

        $reservationsActives = [];
        foreach ($equipements as $equipement) {
            $reservationsActives[$equipement->getId()] = $equipementRepository->findActiveReservation($equipement);
        }

        // Enregistrement d’une réservation (prise de matériel)
        if (
            $request->isMethod('POST')
            && $request->request->get('equipement_id')
        ) {
            $nom = $request->request->get('nom');
            $postnom = $request->request->get('postnom');
            $prenom = $request->request->get('prenom');
            $promotion = $request->request->get('promotion');
            $filiere = $request->request->get('filiere');
            $email = $request->request->get('email');
            $telephone = $request->request->get('telephone');

            if (is_array($promotion)) {
                $promotion = $promotion[0];
            }

            if (in_array($promotion, ['L1', 'L2'], true)) {
                $filiere = 'Science de base';
            } elseif (!$filiere) {
                throw new \InvalidArgumentException('Le champ filière est requis pour les promotions autres que L1 et L2.');
            }

            $equipement = $equipementRepository->find($request->request->get('equipement_id'));
            if ($equipement) {
                $equipement->setEtat(Equipement::ETAT_PRIS);
                $reservation = new Reservation();
                $reservation->setNomPersonne($nom);
                $reservation->setPostnomPersonne($postnom);
                $reservation->setPrenomPersonne($prenom);
                $reservation->setPromotion($promotion);
                $reservation->setFiliere($filiere);
                $reservation->setEmailPersonne($email);
                $reservation->setTelephone($telephone);
                $reservation->setEquipement($equipement);
                $reservation->setDateReservation(new \DateTime());
                $reservation->setActive(true);

                if ($user instanceof \App\Entity\User) {
                    $reservation->setUser($user);
                } else {
                    throw new \LogicException('Utilisateur non valide.');
                }

                $reservationRepository->getEntityManager()->persist($reservation);
                $reservationRepository->getEntityManager()->flush();

                $this->addFlash('success', 'Matériel pris avec succès par ' . $prenom . ' ' . $nom . ' !');
                return $this->redirectToRoute('reservation_index');
            } else {
                $this->addFlash('error', "Cet équipement n'est plus disponible.");
            }
        }

        return $this->render('reservation/index.html.twig', [
            'equipements' => $equipements,
            'reservationsActives' => $reservationsActives,
        ]);
    }

    #[Route('/mes-reservations', name: 'mes_reservations')]
    public function mesReservations(EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Annulation d'une prise (désactivation)
        if (
            $request->isMethod('POST')
            && $request->request->get('reservation_id')
        ) {
            $reservation = $em->getRepository(Reservation::class)->find($request->request->get('reservation_id'));
            if ($reservation && $reservation->getUser() === $user && $reservation->isActive()) {
                $reservation->setActive(false);
                $equipement = $reservation->getEquipement();
                $equipement->setDisponible(true);
                $equipement->setEtat(Equipement::ETAT_DISPONIBLE);
                $em->flush();

                $this->addFlash('success', 'Réservation annulée avec succès.');
                return $this->redirectToRoute('mes_reservations');
            } else {
                $this->addFlash('error', 'Impossible d’annuler cette réservation.');
            }
        }

        $reservations = $em->getRepository(Reservation::class)->findBy(
            ['user' => $user],
            ['dateReservation' => 'DESC']
        );

        return $this->render('reservation/mes_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    /**
     * @Route("/remise-equipement", name="remise_equipement_page")
     */
    #[Route("/remise-equipement", name:"remise_equipement_page")]
    public function remiseEquipementPage(EntityManagerInterface $entityManager)
    {
        $equipements = $entityManager->getRepository(Equipement::class)->findAll();

        return $this->render('reservation/remise_equipement.html.twig', [
            'equipements' => $equipements,
        ]);
    }

    /**
     * @Route("/remise-equipement/{id}", name="remise_equipement", methods={"POST"})
     */
    #[Route('/remise-equipement/{id}', name: 'remise_equipement', methods: ['POST'])]
    public function remiseEquipement($id, EntityManagerInterface $entityManager)
    {
        $equipement = $entityManager->getRepository(Equipement::class)->find($id);

        if ($equipement && $equipement->getEtat() === 'Pris') {
            $equipement->setEtat('Disponible');
            $entityManager->flush();
        }

        return new RedirectResponse($this->generateUrl('admin_equipement_dashboard'));
    }
}
