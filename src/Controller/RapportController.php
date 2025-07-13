<?php

namespace App\Controller;

use App\Repository\EquipementRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\PDFGeneratorService;
use Symfony\Component\HttpFoundation\Request;

class RapportController extends AbstractController
{

    #[Route('/rapport', name: 'app_rapport_index')]
    public function index(
        EquipementRepository  $equipementRepository,
        ReservationRepository $reservationRepository,
        PDFGeneratorService   $pdfGeneratorService,
        Request               $request
    ): Response
    {
        $equipements = $equipementRepository->findAll();
        $reservations = $reservationRepository->findAll();

        // Si le bouton "Télécharger PDF" est cliqué
        if ($request->query->get('format') === 'pdf') {
            return $pdfGeneratorService->generatePdf('rapport/pdf.html.twig', [
                'equipements' => $equipements,
                'reservations' => $reservations,
            ]);
        }

        return $this->render('rapport/index.html.twig', [
            'equipements' => $equipements,
            'reservations' => $reservations,
        ]);
    }
}
