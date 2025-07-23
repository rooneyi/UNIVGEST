<?php
namespace App\Controller;

use App\Repository\EquipementRepository;
use App\Repository\UserRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maintenance')]
class MaintenanceController extends AbstractController
{
    #[Route('/', name: 'admin_maintenance')]
    #[IsGranted('ROLE_GESTIONNAIRE')]
    public function maintenance(EquipementRepository $equipementRepository, Request $request, UserRepository $userRepository, ReservationRepository $reservationRepository): Response
    {
        // On considère qu'un équipement nécessite une maintenance s'il a plus de 100h d'utilisation
        $equipementsMaintenance = [];
        foreach ($equipementRepository->findAll() as $equipement) {
            $usage = $equipement->usage_hours ?? 0;
            if ($usage >= 100) {
                $equipementsMaintenance[] = $equipement;
            }
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
        return $this->render('maintenance.html.twig', [
            'equipementsMaintenance' => $equipementsMaintenance,
            'equipements0_30h' => $equipements0_30h,
            'equipements30h' => $equipements30h,
            'equipements50h' => $equipements50h,
            'equipements80h' => $equipements80h,
            'equipements100h' => $equipements100h,
            'equipementsPlus100h' => $equipementsPlus100h,
            'equipements' => $equipements, // Ajout pour le graphique JS
        ]);
    }
}
