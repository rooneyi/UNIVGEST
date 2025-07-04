<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('/', name: 'admin_user_index')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $users = $em->getRepository(User::class)->findAll();
        // Ajout d'un tableau de menus pour l'admin
        $adminMenus = [
            [
                'label' => 'Utilisateurs',
                'route' => 'admin_user_index',
            ],
            [
                'label' => 'Équipements',
                'route' => 'admin_equipement_index',
            ],
            [
                'label' => 'Réservations',
                'route' => 'admin_reservation_index',
            ],
        ];

        // Filtrage des réservations (si demandé)
        $filter = $request->query->get('reservation_status');
        $reservationRepo = $em->getRepository(\App\Entity\Reservation::class);
        $reservations = null;
        if ($filter === 'active') {
            $reservations = $reservationRepo->findBy(['active' => true], ['dateReservation' => 'DESC']);
        } elseif ($filter === 'inactive') {
            $reservations = $reservationRepo->findBy(['active' => false], ['dateReservation' => 'DESC']);
        } elseif ($filter === 'all') {
            $reservations = $reservationRepo->findBy([], ['dateReservation' => 'DESC']);
        }

        // Notifications (exemple flash)
        if ($request->query->get('notif') === 'new') {
            $this->addFlash('success', 'Nouvelle réservation enregistrée !');
        }
        if ($request->query->get('notif') === 'cancel') {
            $this->addFlash('error', 'Une réservation a été annulée.');
        }

        return $this->render('admin/dashboard.html.twig', [
            'users' => $users,
            'adminMenus' => $adminMenus,
            'reservations' => $reservations,
            'filter' => $filter,
        ]);
    }

    #[Route('/new', name: 'admin_user_new')]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            if (!preg_match('/^[^@]+@esisalama\.org$/', $email)) {
                $error = "L'adresse email doit se terminer par @esisalama.org";
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles([$request->request->get('role')]);
                $user->setPassword($hasher->hashPassword($user, $request->request->get('password')));
                $em->persist($user);
                $em->flush();
                return $this->redirectToRoute('admin_user_index');
            }
        }
        return $this->render('admin/user/new.html.twig', [
            'error' => $error
        ]);
    }

    #[Route('/edit/{id}', name: 'admin_user_edit')]
    public function edit(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            if (!preg_match('/^[^@]+@esisalama\.org$/', $email)) {
                $error = "L'adresse email doit se terminer par @esisalama.org";
            } else {
                $user->setEmail($email);
                $user->setRoles([$request->request->get('role')]);
                $password = $request->request->get('password');
                if ($password) {
                    $user->setPassword($hasher->hashPassword($user, $password));
                }
                $em->flush();
                return $this->redirectToRoute('admin_user_index');
            }
        }
        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'error' => $error
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_user_delete')]
    public function delete(User $user, EntityManagerInterface $em): Response
    {
        $em->remove($user);
        $em->flush();
        return $this->redirectToRoute('admin_user_index');
    }
}
