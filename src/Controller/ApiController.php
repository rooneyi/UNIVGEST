<?php
namespace App\Controller;

use App\Entity\Equipement;
use App\Entity\Reservation;
use App\Repository\EquipementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
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
            'disponible' => $equipement->isDisponible(),
            'etat' => $equipement->getEtat(),
            'reservation_active' => $reservation ? [
                'id' => $reservation->getId(),
                'nom_personne' => $reservation->getNomPersonne(),
                'prenom_personne' => $reservation->getPrenomPersonne(),
                'date_reservation' => $reservation->getDateReservation()->format('Y-m-d H:i:s')
            ] : null
        ]);
    }

    #[Route('/equipement/update-status/{id}', name: 'api_equipement_update_status', methods: ['POST'])]
    public function updateEquipementStatus(
        int $id, 
        Request $request, 
        EquipementRepository $equipementRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $equipement = $equipementRepo->find($id);
        
        if (!$equipement) {
            return new JsonResponse(['error' => 'Équipement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        // Vérification de la clé API ESP32
        $apiKey = $request->headers->get('X-API-Key');
        if ($apiKey !== $_ENV['ESP32_API_KEY'] ?? 'esp32_secret_key') {
            return new JsonResponse(['error' => 'Clé API invalide'], 401);
        }

        if (isset($data['connected'])) {
            $connected = (bool) $data['connected'];
            
            // Si l'équipement est déconnecté physiquement, marquer comme indisponible
            if (!$connected) {
                $equipement->setEtat('maintenance');
                $equipement->setDisponible(false);
            } else {
                // Si reconnecté et pas de réservation active, marquer comme disponible
                $reservation = $equipementRepo->findActiveReservation($equipement);
                if (!$reservation) {
                    $equipement->setEtat('disponible');
                    $equipement->setDisponible(true);
                }
            }
            
            $em->flush();
        }

        return new JsonResponse([
            'success' => true,
            'equipement' => [
                'id' => $equipement->getId(),
                'nom' => $equipement->getNom(),
                'disponible' => $equipement->isDisponible(),
                'etat' => $equipement->getEtat()
            ]
        ]);
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