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
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function dashboard(Request $request,UserRepository $userRepository, EquipementRepository $equipementRepository, ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findAll();
        $filter = null;

        // Calcul des utilisations par jour pour chaque équipement
        $equipementUsagePerDay = [];
        foreach ($equipementRepository->findAll() as $equipement) {
            $usagePerDay = [];
            foreach ($reservations as $reservation) {
                if ($reservation->getEquipement() && $reservation->getEquipement()->getId() === $equipement->getId()) {
                    // On compte la date de remise comme "fin d'utilisation"
                    $date = $reservation->getDateRemise();
                    if ($date) {
                        $dateStr = $date->format('Y-m-d');
                        if (!isset($usagePerDay[$dateStr])) $usagePerDay[$dateStr] = 0;
                        $usagePerDay[$dateStr]++;
                    }
                }
            }
            $equipementUsagePerDay[$equipement->getId()] = $usagePerDay;
        }

        // Filtrage des équipements selon leur temps d'utilisation (exemple: usage_hours)
        $equipements = $equipementRepository->findAll();
        $equipements0_30h = [];
        $equipements30h = [];
        $equipements50h = [];
        $equipements80h = [];
        $equipements100h = [];
        $equipementsPlus100h = [];
        foreach ($equipements as $eq) {
            $usage = $eq->usage_hours ?? 0;
            if ($usage >= 0 && $usage < 30) {
                $equipements0_30h[] = $eq;
            } elseif ($usage >= 30 && $usage < 50) {
                $equipements30h[] = $eq;
            } elseif ($usage >= 50 && $usage < 80) {
                $equipements50h[] = $eq;
            } elseif ($usage >= 80 && $usage < 100) {
                $equipements80h[] = $eq;
            } elseif ($usage >= 100) {
                $equipements100h[] = $eq;
                $equipementsPlus100h[] = $eq;
            }
        }

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
            'equipementUsagePerDay' => $equipementUsagePerDay,

        ]);
    }

    #[Route('/users', name: 'admin_user_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function userIndex(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
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

    #[Route('/user/edit/{id}', name: 'admin_user_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function editUser(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $role = $request->request->get('role');
            $roles = $role ? [$role] : [];
            $password = $request->request->get('password');
            if (!preg_match('/^[^@]+@udbl.ac\.cd$/', $email)) {
                $error = "L'adresse email doit se terminer par @udbl.ac.cd";
            } elseif ($password && strlen($password) < 6) {
                $error = "Le mot de passe doit contenir au moins 6 caractères.";
            } else {
                $user->setEmail($email);
                if (!empty($roles)) {
                    // Ne mettre à jour les rôles que si le rôle a changé
                    if ($user->getRoles() !== $roles) {
                        $user->setRoles($roles);
                    }
                }
                if ($password) {
                    $user->setPassword($hasher->hashPassword($user, $password));
                }
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Utilisateur modifié avec succès.');
                return $this->redirectToRoute('admin_user_index');
            }
        }
        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'error' => $error
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_user_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(User $user, EntityManagerInterface $em): Response
    {
        $em->remove($user);
        $em->flush();
        return $this->redirectToRoute('admin_user_index');
    }



}
