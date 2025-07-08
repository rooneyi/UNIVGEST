# Intégration ESP32 pour le Suivi des Équipements

## Vue d'ensemble

Ce système permet de surveiller en temps réel l'état de connexion des équipements via des modules ESP32. Chaque équipement est équipé d'un ESP32 qui communique avec la plateforme web pour signaler sa disponibilité.

## Fonctionnalités

### 🔌 Détection de Connexion
- Surveillance automatique de la présence physique des équipements
- Mise à jour en temps réel du statut sur la plateforme web
- Notifications visuelles et sonores

### 💡 Indicateurs LED
- **LED Verte** : Équipement disponible et connecté
- **LED Rouge** : Équipement réservé
- **LED Orange** : Équipement en maintenance
- **LED Clignotante** : Équipement déconnecté

### 🔊 Signaux Sonores
- Bip de connexion d'équipement
- Alerte de déconnexion
- Signal d'erreur de communication

## Configuration Matérielle

### Composants Requis
- ESP32 DevKit
- 3 LEDs (verte, rouge, orange)
- Buzzer piezo
- Bouton poussoir (reset)
- Capteur de présence (optique ou magnétique)
- Résistances 220Ω pour les LEDs

### Schéma de Connexion
```
ESP32 Pin | Composant
----------|----------
GPIO 2    | LED Verte (Disponible)
GPIO 4    | LED Rouge (Réservé)
GPIO 5    | LED Orange (Maintenance)
GPIO 18   | Buzzer
GPIO 19   | Bouton Reset (avec pull-up)
GPIO 21   | Capteur de Présence
GND       | Masse commune
3.3V      | Alimentation capteurs
```

## Configuration Logicielle

### 1. Configuration WiFi
Modifiez dans le code Arduino :
```cpp
const char* ssid = "VOTRE_WIFI_SSID";
const char* password = "VOTRE_WIFI_PASSWORD";
```

### 2. Configuration Serveur
```cpp
const char* serverURL = "http://votre-serveur.com/api";
const char* apiKey = "esp32_secret_key";
```

### 3. ID Équipement
Chaque ESP32 doit avoir un ID unique :
```cpp
int equipementId = 1; // Changer pour chaque équipement
```

## API Endpoints

### GET `/api/equipement/status/{id}`
Récupère le statut actuel d'un équipement.

**Réponse :**
```json
{
  "id": 1,
  "nom": "Ordinateur Portable HP",
  "disponible": true,
  "etat": "disponible",
  "reservation_active": null
}
```

### POST `/api/equipement/update-status/{id}`
Met à jour le statut de connexion d'un équipement.

**Headers requis :**
- `X-API-Key`: Clé d'authentification ESP32
- `Content-Type`: application/json

**Body :**
```json
{
  "connected": true,
  "timestamp": 1234567890
}
```

### GET `/api/equipements/all`
Récupère la liste complète des équipements avec leur statut.

## Installation et Déploiement

### 1. Préparation de l'ESP32
1. Installer l'IDE Arduino
2. Ajouter le support ESP32
3. Installer les bibliothèques requises :
   - WiFi
   - HTTPClient
   - ArduinoJson

### 2. Téléchargement du Code
1. Ouvrir `esp32_equipment_monitor.ino`
2. Configurer les paramètres WiFi et serveur
3. Définir l'ID unique de l'équipement
4. Télécharger vers l'ESP32

### 3. Configuration Serveur
1. Ajouter la clé API dans `.env` :
   ```
   ESP32_API_KEY=votre_cle_secrete_esp32
   ```
2. Déployer les nouveaux contrôleurs API

## Surveillance en Temps Réel

### Interface Web
- Indicateur de connexion en temps réel
- Mise à jour automatique des statuts
- Notifications de changement d'état

### Monitoring JavaScript
Le fichier `esp32-monitor.js` gère :
- Polling automatique des statuts
- Mise à jour de l'interface utilisateur
- Support WebSocket (optionnel)

## Dépannage

### Problèmes de Connexion WiFi
- Vérifier les identifiants WiFi
- S'assurer que l'ESP32 est à portée
- Contrôler la stabilité de l'alimentation

### Erreurs de Communication API
- Vérifier l'URL du serveur
- Contrôler la clé API
- Examiner les logs du serveur

### LEDs qui ne s'allument pas
- Vérifier les connexions
- Tester les LEDs individuellement
- Contrôler les résistances

## Sécurité

### Authentification
- Utilisation de clés API pour l'authentification
- Validation des requêtes côté serveur
- Chiffrement des communications (HTTPS recommandé)

### Bonnes Pratiques
- Changer la clé API par défaut
- Utiliser des réseaux WiFi sécurisés
- Mettre à jour régulièrement le firmware

## Évolutions Futures

### Fonctionnalités Prévues
- Support de capteurs supplémentaires (température, humidité)
- Géolocalisation des équipements
- Historique des connexions/déconnexions
- Alertes par email/SMS
- Interface de configuration web pour les ESP32

### Intégrations Possibles
- Système de badges RFID
- Caméras de surveillance
- Capteurs environnementaux
- Intégration avec systèmes existants

## Support

Pour toute question ou problème :
1. Consulter les logs de l'ESP32 via le moniteur série
2. Vérifier les logs du serveur web
3. Tester les endpoints API manuellement
4. Contacter l'équipe de développement

---

**Note :** Ce système est conçu pour être évolutif et peut être adapté selon les besoins spécifiques de l'université.