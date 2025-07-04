<?php
namespace App\Controller;

use App\Entity\Equipement;
use App\Repository\EquipementRepository;
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
}
