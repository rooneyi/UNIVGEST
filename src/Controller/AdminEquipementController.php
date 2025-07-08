<?php
namespace App\Controller;

use App\Entity\Equipement;
use App\Repository\EquipementRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/equipements')]
class AdminEquipementController extends AbstractController
{
    #[Route('/', name: 'admin_equipement_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $equipements = $em->getRepository(Equipement::class)->findAll();
        return $this->render('admin/equipement/index.html.twig', [
            'equipements' => $equipements
        ]);
    }

    #[Route('/new', name: 'admin_equipement_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $etat = $request->request->get('etat');
            $disponible = $request->request->get('disponible') === '1';
            $equipement = new Equipement();
            $equipement->setNom($nom);
            $equipement->setDescription($request->request->get('description'));
            $equipement->setEtat($etat);
            $equipement->setDisponible($disponible);
            $equipement->setCapteurs($request->request->get('capteurs'));
            $em->persist($equipement);
            $em->flush();
            return $this->redirectToRoute('admin_equipement_index');
        }
        return $this->render('admin/equipement/new.html.twig', [
            'error' => $error
        ]);
    }

    #[Route('/edit/{id}', name: 'admin_equipement_edit')]
    public function edit(Equipement $equipement, Request $request, EntityManagerInterface $em): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $equipement->setNom($request->request->get('nom'));
            $equipement->setDescription($request->request->get('description'));
            $equipement->setEtat($request->request->get('etat'));
            $equipement->setDisponible($request->request->get('disponible') === '1');
            $equipement->setCapteurs($request->request->get('capteurs'));
            $em->flush();
            return $this->redirectToRoute('admin_equipement_index');
        }
        return $this->render('admin/equipement/edit.html.twig', [
            'equipement' => $equipement,
            'error' => $error
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_equipement_delete')]
    public function delete(Equipement $equipement, EntityManagerInterface $em): Response
    {
        $em->remove($equipement);
        $em->flush();
        return $this->redirectToRoute('admin_equipement_index');
    }

    #[Route('/dashboard', name: 'admin_equipement_dashboard')]
    public function dashboard(EquipementRepository $equipementRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $equipements = $equipementRepo->findAll();
        $totalEquipements = count($equipements);
        $totalDisponibles = count(array_filter($equipements, fn($e) => $e->getEtat() === 'disponible'));
        $totalReserves = count(array_filter($equipements, fn($e) => $e->getEtat() === 'reservé'));
        $totalMaintenance = count(array_filter($equipements, fn($e) => $e->getEtat() === 'maintenance'));
        $categories = array_unique(array_map(fn($e) => $e->getCapteurs(), $equipements));

        return $this->render('admin/dashboard.html.twig', [
            'equipements' => $equipements,
            'totalEquipements' => $totalEquipements,
            'totalDisponibles' => $totalDisponibles,
            'totalReserves' => $totalReserves,
            'totalMaintenance' => $totalMaintenance,
            'categories' => $categories,
        ]);
    }

    #[Route('/maintenance', name: 'admin_equipement_maintenance')]
    public function maintenance(EntityManagerInterface $em): Response
    {
        $equipementsAll = $em->getRepository(Equipement::class)->findAll();
        $equipements = array_filter($equipementsAll, function($e) {
            $etat = strtolower($e->getEtat());
            return $etat === 'maintenance' || $etat === 'en maintenance';
        });
        return $this->render('admin/equipement_maintenance.html.twig', [
            'equipements' => $equipements,
            'equipementsAll' => $equipementsAll
        ]);
    }

    #[Route('/mes-maintenances', name: 'admin_equipement_mes_maintenances')]
    public function mesMaintenances(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MAINTENANCIER');
        $equipements = $em->getRepository(Equipement::class)->findBy([
            'maintenancier' => $this->getUser()
        ]);
        return $this->render('admin/equipement_mes_maintenances.html.twig', [
            'equipements' => $equipements
        ]);
    }

    #[Route('/affecter/{id}', name: 'admin_equipement_affecter')]
    public function affecter(Equipement $equipement, Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        // Récupérer tous les maintenanciers (filtrage PHP car JSON_CONTAINS n'est pas supporté en SQLite/PostgreSQL)
        $allUsers = $userRepository->findAll();
        $maintenanciers = array_filter($allUsers, function($user) {
            return in_array('ROLE_MAINTENANCIER', $user->getRoles());
        });

        if ($request->isMethod('POST')) {
            $maintenancierId = $request->request->get('maintenancier');
            $maintenancier = $userRepository->find($maintenancierId);
            if ($maintenancier) {
                $equipement->setMaintenancier($maintenancier);
                $em->flush();
                $this->addFlash('success', 'Équipement affecté avec succès !');
                return $this->redirectToRoute('admin_equipement_maintenance');
            } else {
                $this->addFlash('error', 'Aucun maintenancier sélectionné.');
            }
        }
        return $this->render('admin/affecter_maintenancier.html.twig', [
            'equipement' => $equipement,
            'maintenanciers' => $maintenanciers
        ]);
    }
}
