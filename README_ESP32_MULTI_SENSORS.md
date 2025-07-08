# Système ESP32 Multi-Capteurs pour Détection d'Équipements

## Vue d'ensemble

Ce système avancé utilise trois types de capteurs pour une détection précise et fiable des équipements :
- **RFID (RC522)** : Identification unique de l'équipement
- **Capteur de poids (HX711)** : Vérification de la présence physique
- **Capteur ultrasonique (HC-SR04)** : Mesure de distance pour confirmation

## Architecture du Système

### 🧠 Logique de Détection Intelligente

Le système utilise un **algorithme de scoring** pour déterminer la présence d'un équipement :

1. **RFID (40% du score)** : Identification primaire
2. **Poids (35% du score)** : Confirmation physique
3. **Distance (25% du score)** : Validation spatiale

**Seuil de détection** : 50% minimum pour considérer l'équipement présent.

### 📊 États Possibles

- **`available`** : Présent et disponible (score ≥ 80%)
- **`available_low_confidence`** : Présent mais confiance moyenne (50-79%)
- **`reserved`** : Présent et réservé
- **`absent`** : Physiquement absent (score < 50%)
- **`maintenance`** : Présent mais données incohérentes

## Configuration Matérielle

### Composants Requis

| Composant | Modèle | Quantité | Usage |
|-----------|--------|----------|-------|
| Microcontrôleur | ESP32 DevKit | 1 | Contrôleur principal |
| Lecteur RFID | RC522 | 1 | Identification équipement |
| Capteur de poids | HX711 + Load Cell | 1 | Détection présence physique |
| Capteur ultrasonique | HC-SR04 | 1 | Mesure de distance |
| LEDs | 5mm | 4 | Indicateurs visuels |
| Buzzer | Piezo | 1 | Signaux sonores |
| Boutons | Tactile | 2 | Calibrage et reset |
| Résistances | 220Ω | 4 | Protection LEDs |

### Schéma de Connexion

```
ESP32 Pin | Composant        | Description
----------|------------------|------------------
GPIO 21   | RC522 SDA        | RFID Chip Select
GPIO 22   | RC522 RST        | RFID Reset
GPIO 19   | RC522 MOSI       | SPI Data Out
GPIO 23   | RC522 MISO       | SPI Data In
GPIO 18   | RC522 SCK        | SPI Clock
GPIO 4    | HX711 DOUT       | Données poids
GPIO 5    | HX711 SCK        | Clock poids
GPIO 12   | HC-SR04 TRIG     | Trigger ultrasonique
GPIO 14   | HC-SR04 ECHO     | Echo ultrasonique
GPIO 2    | LED Verte        | Disponible
GPIO 15   | LED Rouge        | Réservé
GPIO 16   | LED Orange       | Maintenance
GPIO 17   | LED Bleue        | Absent
GPIO 18   | Buzzer           | Signaux sonores
GPIO 19   | Bouton Calibrage | Calibrage capteurs
GPIO 23   | Bouton Reset     | Reset système
3.3V      | Alimentation     | Capteurs logiques
5V        | Alimentation     | HX711, HC-SR04
GND       | Masse commune    | Tous composants
```

## Installation et Configuration

### 1. Préparation de l'IDE Arduino

```bash
# Installer les bibliothèques requises
- WiFi (ESP32 Core)
- HTTPClient (ESP32 Core)
- ArduinoJson (v6.x)
- MFRC522 (RFID)
- HX711 (Capteur de poids)
- NewPing (Capteur ultrasonique)
```

### 2. Configuration du Code

```cpp
// Configuration WiFi
const char* ssid = "VOTRE_WIFI_SSID";
const char* password = "VOTRE_WIFI_PASSWORD";

// Configuration serveur
const char* serverURL = "http://votre-serveur.com/api";
const char* apiKey = "esp32_secret_key";

// ID unique de l'équipement
int equipementId = 1; // À changer pour chaque ESP32
```

### 3. Calibrage Initial

1. **Appuyer sur le bouton CALIBRAGE**
2. **Placer l'équipement** sur le capteur de poids
3. **Présenter le tag RFID** au lecteur
4. **Attendre 10 secondes** pour la calibrage automatique
5. **Vérifier les LEDs** de confirmation

## API et Communication

### Endpoints Disponibles

#### `POST /api/equipement/sensor-data/{id}`
Réception des données capteurs ESP32.

**Payload :**
```json
{
  "rfid_detected": true,
  "rfid_tag": "A1B2C3D4",
  "weight": 1250.5,
  "distance": 15,
  "timestamp": 1234567890,
  "equipment_id": 1,
  "reference_data": {
    "rfid_tag": "A1B2C3D4",
    "weight": 1250.0,
    "distance": 15
  },
  "sensor_status": {
    "rfid_reader": true,
    "weight_sensor": true,
    "ultrasonic": true
  }
}
```

**Réponse :**
```json
{
  "success": true,
  "detection_result": {
    "physically_present": true,
    "confidence_score": 85,
    "confidence_percentage": 85,
    "detection_details": {
      "rfid": "MATCH",
      "poids": "EXACT_MATCH",
      "distance": "APPROXIMATE_MATCH"
    },
    "suggested_state": "available",
    "timestamp": "2025-01-07 10:30:45"
  },
  "equipement": {
    "id": 1,
    "nom": "Ordinateur Portable HP",
    "disponible": true,
    "etat": "disponible",
    "physically_present": true
  }
}
```

#### `POST /api/equipement/calibrate/{id}`
Calibrage des capteurs.

**Payload :**
```json
{
  "rfid_tag": "A1B2C3D4",
  "reference_weight": 1250.0,
  "reference_distance": 15,
  "calibration_timestamp": 1234567890
}
```

## Algorithme de Détection

### Scoring System

```php
// Poids des critères
RFID_WEIGHT = 40%
POIDS_WEIGHT = 35%
DISTANCE_WEIGHT = 25%

// Calcul du score
score = 0

// Vérification RFID
if (rfid_tag == reference_tag) {
    score += 40
} else if (rfid_detected && rfid_tag != reference_tag) {
    score -= 20  // Mauvais tag
}

// Vérification poids
ecart_poids = |poids_actuel - poids_reference| / poids_reference
if (ecart_poids <= 0.05) {      // 5% tolérance
    score += 35
} else if (ecart_poids <= 0.15) { // 15% tolérance
    score += 20
}

// Vérification distance
ecart_distance = |distance_actuelle - distance_reference|
if (ecart_distance <= 2) {      // 2cm tolérance
    score += 25
} else if (ecart_distance <= 10) { // 10cm tolérance
    score += 15
}

// Détermination finale
present = (score >= 50)
```

### Gestion des États

```php
if (!present) {
    return 'absent';
}

if (reservation_active) {
    return 'reserved';
}

if (score >= 80) {
    return 'available';
} else if (score >= 50) {
    return 'available_low_confidence';
} else {
    return 'maintenance';
}
```

## Fonctionnalités Avancées

### 🔄 Historique et Stabilité

- **Moyenne mobile** sur 5 mesures pour stabiliser les lectures
- **Historique JSON** des 100 dernières mesures
- **Détection de tendances** pour prédire les pannes

### 🔧 Auto-Calibrage

- **Calibrage automatique** en appuyant sur un bouton
- **Sauvegarde EEPROM** des valeurs de référence
- **Synchronisation serveur** des paramètres

### 🚨 Alertes et Notifications

- **LEDs colorées** selon l'état
- **Signaux sonores** différenciés
- **Notifications temps réel** via WebSocket

### 📊 Monitoring Avancé

- **Tableau de bord** temps réel
- **Graphiques de tendances** des capteurs
- **Alertes de maintenance** prédictive

## Dépannage

### Problèmes Courants

#### RFID ne lit pas
```
- Vérifier les connexions SPI
- Tester avec un tag connu
- Vérifier l'alimentation 3.3V
- Distance optimale : 2-5cm
```

#### Capteur de poids instable
```
- Calibrer avec un poids connu
- Vérifier les connexions HX711
- Éviter les vibrations
- Utiliser la moyenne mobile
```

#### Distance incorrecte
```
- Vérifier TRIG et ECHO
- Obstacles dans le champ
- Température affecte la précision
- Distance max : 200cm
```

#### Connexion WiFi échoue
```
- Vérifier SSID/mot de passe
- Signal WiFi suffisant
- Redémarrer l'ESP32
- Vérifier l'alimentation
```

### Codes d'Erreur LED

| Pattern LED | Signification |
|-------------|---------------|
| Toutes clignotent | Démarrage |
| Verte fixe | Disponible |
| Rouge fixe | Réservé |
| Orange fixe | Maintenance |
| Bleue fixe | Absent |
| Orange clignote | Calibrage |
| Rouge clignote | Erreur |

## Évolutions Futures

### 🔮 Fonctionnalités Prévues

- **IA/ML** pour détection prédictive
- **Capteurs environnementaux** (température, humidité)
- **Caméra** pour reconnaissance visuelle
- **Blockchain** pour traçabilité
- **Application mobile** dédiée

### 🌐 Intégrations

- **Systèmes ERP** existants
- **Badges étudiants** RFID
- **Systèmes de sécurité** du campus
- **Maintenance prédictive** IoT

## Support et Maintenance

### Logs et Diagnostics

```cpp
// Activation des logs détaillés
#define DEBUG_MODE 1
#define SERIAL_SPEED 115200

// Commandes de diagnostic
Serial.println("=== DIAGNOSTIC ESP32 ===");
Serial.println("WiFi: " + WiFi.localIP().toString());
Serial.println("RFID: " + String(mfrc522.PCD_PerformSelfTest()));
Serial.println("Poids: " + String(scale.is_ready()));
Serial.println("Distance: " + String(sonar.ping_cm()));
```

### Maintenance Préventive

- **Nettoyage RFID** : Mensuel
- **Calibrage poids** : Trimestriel  
- **Vérification connexions** : Semestriel
- **Mise à jour firmware** : Selon besoins

---

**Note :** Ce système est conçu pour être robuste, précis et évolutif. Il peut être adapté selon les besoins spécifiques de chaque type d'équipement universitaire.