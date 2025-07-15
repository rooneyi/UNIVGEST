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

        // Vérification des réservations non remises dont la date de remise prévue est dépassée
        $now = new \DateTime();
        $reservationsNonRemises = $reservationRepository->createQueryBuilder('r')
            ->where('r.active = true')
            ->andWhere('r.dateRemisePrevue IS NOT NULL')
            ->andWhere('r.dateRemisePrevue < :now')
            ->setParameter('now', $now)
            ->getQuery()->getResult();
        if ($reservationsNonRemises) {
            foreach ($reservationsNonRemises as $res) {
                $this->addFlash('error', 'Attention : L\'équipement "' . $res->getEquipement()->getNom() . '" n\'a pas été remis dans le délai de 24h !');
            }
        }

        // Enregistrement d’une réservation (prise de matériel)
        if (
            $request->isMethod('POST')
            && $request->request->has('equipement_ids')
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

            $equipementIds = $request->request->all('equipement_ids');
            if (!is_array($equipementIds) || count($equipementIds) === 0) {
                $this->addFlash('error', 'Veuillez sélectionner au moins un équipement disponible.');
                return $this->redirectToRoute('reservation_index');
            }
            $success = [];
            $errors = [];
            foreach ($equipementIds as $equipementId) {
                $equipement = $equipementRepository->find($equipementId);
                if ($equipement && $equipement->getEtat() === Equipement::ETAT_DISPONIBLE) {
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
                    $dateRemisePrevue = (new \DateTime())->modify('+24 hours');
                    $reservation->setDateRemisePrevue($dateRemisePrevue);
                    $reservation->setActive(true);
                    if ($user instanceof \App\Entity\User) {
                        $reservation->setUser($user);
                    }
                    $reservationRepository->getEntityManager()->persist($reservation);
                    $success[] = $equipement->getNom();
                } else {
                    $errors[] = $equipement ? $equipement->getNom() : 'Inconnu';
                }
            }
            $reservationRepository->getEntityManager()->flush();
            if ($success) {
                $this->addFlash('success', 'Matériel pris avec succès : ' . implode(', ', $success));
            }
            if ($errors) {
                $this->addFlash('error', 'Certains équipements ne sont pas disponibles : ' . implode(', ', $errors));
            }
            return $this->redirectToRoute('reservation_index');
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
    public function remiseEquipement($id, EntityManagerInterface $entityManager, Request $request)
    {
        $email = $request->request->get('email');
        if ($email && !preg_match('/^[^@]+@udbl\.ac\.cd$/', $email)) {
            $this->addFlash('error', "L'adresse email doit se terminer par @udbl.ac.cd");
            return new RedirectResponse($this->generateUrl('admin_dashboard'));
        }
        $equipement = $entityManager->getRepository(Equipement::class)->find($id);
        if ($equipement && $equipement->getEtat() === 'Pris') {
            $equipement->setEtat('Disponible');
            // Chercher la réservation active liée à cet équipement
            $reservation = $entityManager->getRepository(Reservation::class)->findOneBy([
                'equipement' => $equipement,
                'active' => true
            ]);
            if ($reservation) {
                $reservation->setDateRemise(new \DateTime());
                $reservation->setActive(false);
                $entityManager->persist($reservation);
            }
            $entityManager->flush();
        }
        return new RedirectResponse($this->generateUrl('admin_dashboard'));
    }
}
