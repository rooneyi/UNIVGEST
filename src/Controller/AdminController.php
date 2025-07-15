<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\EquipementRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Cassandra\Type\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function dashboard(Request $request,UserRepository $userRepository, EquipementRepository $equipementRepository, ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findAll();
        $filter = null;


        // Notifications (exemple flash)
        if ($request->query->get('notif') === 'new') {
            $this->addFlash('success', 'Nouvelle Prise d\'un equipement enregistrée !');
        }
        if ($request->query->get('notif') === 'cancel') {
            $this->addFlash('error', 'Une Prise d\'un equipement a été annulée.');
        }

        return $this->render('admin/dashboard.html.twig', [
            'users' => $userRepository->findAll(),
            'prise' => $reservations,
            'filter' => $filter,
            'equipements' => $equipementRepository->findAll(),

        ]);
    }

    #[Route('/users', name: 'admin_user_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function userIndex(UserRepository $userRepository): Response
    {
        $users = $userRepository->findBy(["roles" => ["ROLE_GESTIONNAIRE"]]);
        return $this->render('admin/user/index.html.twig', [
            'users' => $users
        ]);
    }

    #[Route('/user/new', name: 'admin_user_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function userNew(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            if (!preg_match('/^[^@]+@udbl.ac\.cd$/', $email)) {
                $error = "L'adresse email doit se terminer par @udbl.ac.cd";
            } elseif (strlen($password) < 6) {
                $error = "Le mot de passe doit contenir au moins 6 caractères.";
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $error = "Un compte existe déjà avec cette adresse email.";
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles(['ROLE_GESTIONNAIRE']);
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Gestionnaire créé avec succès.');
                return $this->redirectToRoute('admin_user_index');
            }
        }
        return $this->render('admin/user/new.html.twig', [
            'error' => $error
        ]);
    }


    #[Route('/delete/{id}', name: 'admin_user_delete')]
    public function delete(User $user, EntityManagerInterface $em): Response
    {
        $em->remove($user);
        $em->flush();
        return $this->redirectToRoute('admin_dashboard');
    }
}
