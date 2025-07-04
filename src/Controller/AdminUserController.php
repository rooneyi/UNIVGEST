<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
class AdminUserController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function dashboard(Request $request, UserRepository $userRepository, ReservationRepository $reservationRepository): Response
    {
        $users = $userRepository->findAll();
        $adminMenus = []; // À adapter selon ton menu
        $reservations = $reservationRepository->findAll();
        $filter = null; // À adapter si besoin

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
