<?php
namespace App\Service;

use App\Entity\Equipement;
use App\Repository\EquipementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EquipementDetectionService
{
    private EntityManagerInterface $em;
    private EquipementRepository $equipementRepo;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        EquipementRepository $equipementRepo,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->equipementRepo = $equipementRepo;
        $this->logger = $logger;
    }

    /**
     * Traite les données des capteurs ESP32 et met à jour l'état de l'équipement
     */
    public function traiterDonneesESP32(int $equipementId, array $donneesCapteursESP32): array
    {
        $equipement = $this->equipementRepo->find($equipementId);
        
        if (!$equipement) {
            throw new \InvalidArgumentException("Équipement avec l'ID {$equipementId} non trouvé");
        }

        // Extraction des données capteurs
        $rfidDetecte = $donneesCapteursESP32['rfid_detected'] ?? false;
        $rfidTag = $donneesCapteursESP32['rfid_tag'] ?? null;
        $poids = $donneesCapteursESP32['weight'] ?? null;
        $distance = $donneesCapteursESP32['distance'] ?? null;
        $timestamp = new \DateTime();

        // Mise à jour des données actuelles
        if ($poids !== null) {
            $equipement->setPoidsActuel($poids);
        }
        
        if ($distance !== null) {
            $equipement->setDistanceActuelle($distance);
        }
        
        $equipement->setDerniereMiseAJour($timestamp);

        // Ajout à l'historique
        $equipement->ajouterDonneesHistorique([
            'rfid_detected' => $rfidDetecte,
            'rfid_tag' => $rfidTag,
            'weight' => $poids,
            'distance' => $distance,
            'physically_present' => null // Sera calculé après
        ]);

        // Logique de détection intelligente
        $resultatDetection = $this->analyserPresenceEquipement($equipement, $donneesCapteursESP32);
        
        // Mise à jour de l'état selon la détection
        $this->mettreAJourEtatEquipement($equipement, $resultatDetection);
        
        $this->em->flush();

        $this->logger->info("Données ESP32 traitées pour équipement {$equipementId}", [
            'resultat_detection' => $resultatDetection,
            'donnees_capteurs' => $donneesCapteursESP32
        ]);

        return $resultatDetection;
    }

    /**
     * Analyse intelligente de la présence de l'équipement
     */
    private function analyserPresenceEquipement(Equipement $equipement, array $donneesCapteursESP32): array
    {
        $rfidDetecte = $donneesCapteursESP32['rfid_detected'] ?? false;
        $rfidTag = $donneesCapteursESP32['rfid_tag'] ?? null;
        $poids = $donneesCapteursESP32['weight'] ?? null;
        $distance = $donneesCapteursESP32['distance'] ?? null;

        $score = 0;
        $details = [];
        $confiance = 0;

        // 1. Vérification RFID (poids: 40%)
        if ($rfidDetecte && $rfidTag === $equipement->getRfidTag()) {
            $score += 40;
            $details['rfid'] = 'MATCH';
            $confiance += 40;
        } elseif ($rfidDetecte && $rfidTag !== $equipement->getRfidTag()) {
            $score -= 20; // Mauvais tag détecté
            $details['rfid'] = 'WRONG_TAG';
        } else {
            $details['rfid'] = 'NOT_DETECTED';
        }

        // 2. Vérification du poids (poids: 35%)
        if ($poids !== null && $equipement->getPoidsReference() !== null) {
            $ecartPoids = abs($poids - $equipement->getPoidsReference()) / $equipement->getPoidsReference();
            
            if ($ecartPoids <= 0.05) { // 5% de tolérance
                $score += 35;
                $details['poids'] = 'EXACT_MATCH';
                $confiance += 35;
            } elseif ($ecartPoids <= 0.15) { // 15% de tolérance
                $score += 20;
                $details['poids'] = 'APPROXIMATE_MATCH';
                $confiance += 20;
            } else {
                $score -= 10;
                $details['poids'] = 'NO_MATCH';
            }
        } else {
            $details['poids'] = 'NO_REFERENCE';
        }

        // 3. Vérification de la distance (poids: 25%)
        if ($distance !== null && $equipement->getDistanceReference() !== null) {
            $ecartDistance = abs($distance - $equipement->getDistanceReference());
            
            if ($ecartDistance <= 2) { // 2cm de tolérance
                $score += 25;
                $details['distance'] = 'EXACT_MATCH';
                $confiance += 25;
            } elseif ($ecartDistance <= 10) { // 10cm de tolérance
                $score += 15;
                $details['distance'] = 'APPROXIMATE_MATCH';
                $confiance += 15;
            } else {
                $score -= 5;
                $details['distance'] = 'NO_MATCH';
            }
        } else {
            $details['distance'] = 'NO_REFERENCE';
        }

        // Détermination de l'état final
        $present = $score >= 50; // Seuil de 50%
        $etatSuggere = $this->determinerEtatSuggere($present, $score, $equipement);

        return [
            'physically_present' => $present,
            'confidence_score' => $score,
            'confidence_percentage' => min(100, $confiance),
            'detection_details' => $details,
            'suggested_state' => $etatSuggere,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Détermine l'état suggéré de l'équipement
     */
    private function determinerEtatSuggere(bool $present, int $score, Equipement $equipement): string
    {
        if (!$present) {
            return 'absent'; // Équipement physiquement absent
        }

        // Si présent, vérifier s'il y a une réservation active
        $reservationActive = $this->equipementRepo->findActiveReservation($equipement);
        
        if ($reservationActive) {
            return 'reserved'; // Présent et réservé
        }

        if ($score >= 80) {
            return 'available'; // Présent et disponible avec haute confiance
        } elseif ($score >= 50) {
            return 'available_low_confidence'; // Présent mais confiance moyenne
        }

        return 'maintenance'; // Présent mais données incohérentes
    }

    /**
     * Met à jour l'état de l'équipement selon les résultats de détection
     */
    private function mettreAJourEtatEquipement(Equipement $equipement, array $resultatDetection): void
    {
        $etatSuggere = $resultatDetection['suggested_state'];
        $present = $resultatDetection['physically_present'];

        switch ($etatSuggere) {
            case 'absent':
                $equipement->setEtat('absent');
                $equipement->setDisponible(false);
                break;
                
            case 'reserved':
                $equipement->setEtat('reservé');
                $equipement->setDisponible(false);
                break;
                
            case 'available':
                $equipement->setEtat('disponible');
                $equipement->setDisponible(true);
                break;
                
            case 'available_low_confidence':
                $equipement->setEtat('disponible');
                $equipement->setDisponible(true);
                // Ajouter une note dans l'historique
                break;
                
            case 'maintenance':
                $equipement->setEtat('maintenance');
                $equipement->setDisponible(false);
                break;
        }
    }

    /**
     * Calibre les capteurs pour un équipement
     */
    public function calibrerEquipement(int $equipementId, array $donneesCalibrage): bool
    {
        $equipement = $this->equipementRepo->find($equipementId);
        
        if (!$equipement) {
            return false;
        }

        if (isset($donneesCalibrage['rfid_tag'])) {
            $equipement->setRfidTag($donneesCalibrage['rfid_tag']);
        }

        if (isset($donneesCalibrage['reference_weight'])) {
            $equipement->setPoidsReference($donneesCalibrage['reference_weight']);
        }

        if (isset($donneesCalibrage['reference_distance'])) {
            $equipement->setDistanceReference($donneesCalibrage['reference_distance']);
        }

        $this->em->flush();

        $this->logger->info("Équipement {$equipementId} calibré", $donneesCalibrage);

        return true;
    }
}