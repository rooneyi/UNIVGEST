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

        // Mise à jour des données capteurs
        if (isset($data['rfid_tag'])) {
            $equipement->setRfidTag($data['rfid_tag']);
        }
        if (isset($data['weight'])) {
            $equipement->setPoidsActuel($data['weight']);
        }
        if (isset($data['distance1'])) {
            $equipement->setDistance1($data['distance1']);
        }
        if (isset($data['distance2'])) {
            $equipement->setDistance2($data['distance2']);
        }
        $equipement->setDerniereMiseAJour(new \DateTime());
        $equipementRepo->getEntityManager()->flush();

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

    #[Route('/equipements/realtime', name: 'api_equipements_realtime', methods: ['GET'])]
    public function getEquipementsRealtime(EquipementRepository $equipementRepo): JsonResponse
    {
        $equipements = $equipementRepo->findAll();
        $result = [];
        foreach ($equipements as $equipement) {
            // Exclure les équipements déclassés
            if (strtolower($equipement->getEtat()) === 'declasser' || strtolower($equipement->getEtat()) === 'déclassé') {
                continue;
            }
            // Extraction des données capteurs pour chaque compartiment
            $historique = $equipement->getDonneesCapteursHistorique();
            $last = [];
            if (!empty($historique)) {
                $last = end($historique);
            }
            $sensorData = [
                'compartiment' => $equipement->getCompartiment(),
                'ultrason' => [
                    'distance1' => $last['distance1'] ?? $equipement->getDistance1(),
                    'distance2' => $last['distance2'] ?? $equipement->getDistance2(),
                ],
                'poids' => $last['weight'] ?? $equipement->getPoidsActuel(),
                'presence' => $last['presence'] ?? null,
                'buzzer' => $last['buzzer'] ?? null,
            ];
            $result[] = [
                'id' => $equipement->getId(),
                'code' => $equipement->getCode(),
                'nom' => $equipement->getNom(),
                'etat' => $equipement->getEtat(),
                'physically_present' => $equipement->isPhysiquementPresent(),
                'last_update' => $equipement->getDerniereMiseAJour()?->format('Y-m-d H:i:s'),
                'poids' => $equipement->getPoidsActuel(),
                'weight' => $equipement->getPoidsActuel(),
                'distance1' => $equipement->getDistance1(),
                'distance2' => $equipement->getDistance2(),
                'rfid_tag' => $equipement->getRfidTag(),
                'usage_hours' => $equipement->getTempsUtilisationTotal(),
                'sensor_data' => $sensorData,
                'compartiment' => $equipement->getCompartiment(),
            ];
        }
        return new JsonResponse($result);
    }
}
