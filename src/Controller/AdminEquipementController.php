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
            $disponible = $request->request->get('disponible');
            $equipement = new Equipement();
            $equipement->setNom($nom);
            $equipement->setDescription($request->request->get('description'));
            $equipement->setEtat($etat);
            $equipement->setCapteurs($request->request->get('capteurs'));
            $equipement->setCode($request->request->get('code'));
            $equipement->setCompartiment($request->request->get('compartiment'));
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
            $etat = $request->request->get('etat');
            if ($etat === null) {
                throw new \RuntimeException('La valeur de l\'état est null. Vérifiez le formulaire HTML et la soumission.');
            }
            $equipement->setEtat($etat);
            $equipement->setCode($request->request->get('code'));
            $equipement->setCapteurs($request->request->get('capteurs'));
            $equipement->setCompartiment($request->request->get('compartiment'));
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


    #[Route('/sortie', name: 'admin_equipement_sortie')]
    public function sortie(EntityManagerInterface $em, Request $request): Response
    {
        $equipementsDisponibles = $em->getRepository(Equipement::class)->findBy(['etat' => 'Disponible']);

        if ($request->isMethod('POST')) {
            $equipementIds = $request->request->all()['equipements'] ?? [];
            if (!is_array($equipementIds)) {
                $equipementIds = [];
            }
            foreach ($equipementIds as $id) {
                $equipement = $em->getRepository(Equipement::class)->find($id);
                if ($equipement && $equipement->getEtat() !== null) {
                    $etat = $equipement->getEtat() ?? 'Disponible'; // Utiliser une valeur par défaut si null
                    if (!is_string($etat)) {
                        throw new \InvalidArgumentException('La valeur de l\'état doit être une chaîne valide.');
                    }
                    $equipement->setEtat('Indisponible');
                    $em->persist($equipement);
                }
            }
            $em->flush();
            $this->addFlash('success', 'La sortie des équipements a été validée avec succès.');
            return $this->redirectToRoute('admin_equipement_sortie');
        }

        return $this->render('admin/equipement/sortie.html.twig', [
            'equipements' => $equipementsDisponibles
        ]);
    }

    #[Route('/declasser/{id}', name: 'admin_equipement_declasser', methods: ['POST'])]
    public function declasser(Equipement $equipement, EntityManagerInterface $em): Response
    {
        $equipement->setEtat('Déclassé');
        $em->flush();
        $this->addFlash('success', 'Équipement déclassé avec succès.');
        return $this->redirectToRoute('admin_equipement_index');
    }
}
