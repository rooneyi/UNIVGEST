<?php
namespace App\Controller;

use App\Entity\Equipement;
use App\Entity\Reservation;
use App\Repository\EquipementRepository;
use App\Service\EquipementDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    private EquipementDetectionService $detectionService;

    public function __construct(EquipementDetectionService $detectionService)
    {
        $this->detectionService = $detectionService;
    }

    #[Route('/equipement/status/{id}', name: 'api_equipement_status', methods: ['GET'])]
    public function getEquipementStatus(int $id, EquipementRepository $equipementRepo): JsonResponse
    {
        $equipement = $equipementRepo->find($id);

        if (!$equipement) {
            return new JsonResponse(['error' => 'Équipement non trouvé'], 404);
        }

        $reservation = $equipementRepo->findActiveReservation($equipement);

        return new JsonResponse([
            'id' => $equipement->getId(),
            'nom' => $equipement->getNom(),
            'etat' => $equipement->getEtat(),
            'physically_present' => $equipement->isPhysiquementPresent(),
            'last_update' => $equipement->getDerniereMiseAJour()?->format('Y-m-d H:i:s'),
            'sensor_data' => [
                'weight' => $equipement->getPoidsActuel(),
                'distance' => $equipement->getDistanceActuelle(),
                'rfid_tag' => $equipement->getRfidTag()
            ],
            'reservation_active' => $reservation ? [
                'id' => $reservation->getId(),
                'nom_personne' => $reservation->getNomPersonne(),
                'prenom_personne' => $reservation->getPrenomPersonne(),
                'date_reservation' => $reservation->getDateReservation()->format('Y-m-d H:i:s')
            ] : null
        ]);
    }

    #[Route('/equipement/sensor-data/{id}', name: 'api_equipement_sensor_data', methods: ['GET','POST'])]
    public function receiveSensorData(
        int $id,
        Request $request,
        EquipementRepository $equipementRepo
    ): JsonResponse {
        $equipement = $equipementRepo->find($id);

        if (!$equipement) {
            return new JsonResponse(['error' => 'Équipement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

//        // Vérification de la clé API ESP32
//        $apiKey = $request->headers->get('X-API-Key');
//        if ($apiKey !== $_ENV['ESP32_API_KEY'] ?? 'esp32_secret_key') {
//            return new JsonResponse(['error' => 'Clé API invalide'], 401);
//        }

        try {
            $resultatDetection = $this->detectionService->traiterDonneesESP32($id, $data);

            return new JsonResponse([
                'success' => true,
                'detection_result' => $resultatDetection,
                'equipement' => [
                    'id' => $equipement->getId(),
                    'nom' => $equipement->getNom(),
                    'disponible' => $equipement->isDisponible(),
                    'etat' => $equipement->getEtat(),
                    'physically_present' => $equipement->isPhysiquementPresent()
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/equipement/calibrate/{id}', name: 'api_equipement_calibrate', methods: ['POST'])]
    public function calibrateEquipement(
        int $id,
        Request $request,
        Equipement $equipement
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Vérification de la clé API
        $apiKey = $request->headers->get('X-API-Key');
        if ($apiKey !== $_ENV['ESP32_API_KEY'] ?? 'esp32_secret_key') {
            return new JsonResponse(['error' => 'Clé API invalide'], 401);
        }

        $success = $this->detectionService->calibrerEquipement($id, $data);

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Calibrage réussi']);
        } else {
            return new JsonResponse(['error' => 'Équipement non trouvé'], 404);
        }
    }

    #[Route('/equipements/all', name: 'api_equipements_all', methods: ['GET'])]
    public function getAllEquipements(EquipementRepository $equipementRepo): JsonResponse
    {
        $equipements = $equipementRepo->findAll();
        $data = [];

        foreach ($equipements as $equipement) {
            $reservation = $equipementRepo->findActiveReservation($equipement);

            $data[] = [
                'id' => $equipement->getId(),
                'nom' => $equipement->getNom(),
                'description' => $equipement->getDescription(),
                'disponible' => $equipement->isDisponible(),
                'etat' => $equipement->getEtat(),
                'capteurs' => $equipement->getCapteurs(),
                'reservation_active' => $reservation ? [
                    'nom_personne' => $reservation->getNomPersonne(),
                    'prenom_personne' => $reservation->getPrenomPersonne(),
                    'date_reservation' => $reservation->getDateReservation()->format('Y-m-d H:i:s')
                ] : null
            ];
        }

        return new JsonResponse($data);
    }
}
