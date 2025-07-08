#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <HX711.h>
#include <NewPing.h>
#include <EEPROM.h>

// Configuration WiFi
const char* ssid = "VOTRE_WIFI_SSID";
const char* password = "VOTRE_WIFI_PASSWORD";

// Configuration serveur
const char* serverURL = "http://votre-serveur.com/api";
const char* apiKey = "esp32_secret_key";

// Pins RFID (RC522)
#define RST_PIN         22
#define SS_PIN          21
MFRC522 mfrc522(SS_PIN, RST_PIN);

// Pins capteur de poids (HX711)
#define LOADCELL_DOUT_PIN  4
#define LOADCELL_SCK_PIN   5
HX711 scale;

// Pins capteur ultrasonique (HC-SR04)
#define TRIGGER_PIN     12
#define ECHO_PIN        14
#define MAX_DISTANCE    200  // Distance maximale en cm
NewPing sonar(TRIGGER_PIN, ECHO_PIN, MAX_DISTANCE);

// Pins LEDs et contrôles
const int LED_DISPONIBLE = 2;    // LED verte
const int LED_RESERVE = 15;      // LED rouge  
const int LED_MAINTENANCE = 16;  // LED orange
const int LED_ABSENT = 17;       // LED bleue
const int BUZZER = 18;
const int BUTTON_CALIBRATE = 19;
const int BUTTON_RESET = 23;

// Variables globales
int equipementId = 1;
String rfidTagReference = "";
float poidsReference = 0.0;
int distanceReference = 0;

// Variables de mesure
String dernierTagRFID = "";
bool rfidDetecte = false;
float poidsActuel = 0.0;
int distanceActuelle = 0;

// Timing
unsigned long lastSensorRead = 0;
unsigned long lastServerUpdate = 0;
const unsigned long sensorInterval = 1000;    // Lecture capteurs toutes les 1s
const unsigned long serverInterval = 5000;    // Envoi serveur toutes les 5s

// Calibrage
bool modeCalibrage = false;
float facteurCalibragePoids = 1.0;

// Historique pour stabilité
const int HISTORIQUE_SIZE = 5;
float historiqueDistance[HISTORIQUE_SIZE];
float historiquePoids[HISTORIQUE_SIZE];
int indexHistorique = 0;

void setup() {
  Serial.begin(115200);
  
  // Initialisation des pins
  pinMode(LED_DISPONIBLE, OUTPUT);
  pinMode(LED_RESERVE, OUTPUT);
  pinMode(LED_MAINTENANCE, OUTPUT);
  pinMode(LED_ABSENT, OUTPUT);
  pinMode(BUZZER, OUTPUT);
  pinMode(BUTTON_CALIBRATE, INPUT_PULLUP);
  pinMode(BUTTON_RESET, INPUT_PULLUP);
  
  // Initialisation SPI pour RFID
  SPI.begin();
  mfrc522.PCD_Init();
  
  // Initialisation capteur de poids
  scale.begin(LOADCELL_DOUT_PIN, LOADCELL_SCK_PIN);
  
  // Initialisation EEPROM
  EEPROM.begin(512);
  chargerConfiguration();
  
  // Connexion WiFi
  connectToWiFi();
  
  // Signal de démarrage
  signalStartup();
  
  Serial.println("=== ESP32 Multi-Sensor Equipment Monitor ===");
  Serial.println("ID Équipement: " + String(equipementId));
  Serial.println("RFID Tag Référence: " + rfidTagReference);
  Serial.println("Poids Référence: " + String(poidsReference) + "g");
  Serial.println("Distance Référence: " + String(distanceReference) + "cm");
  Serial.println("============================================");
  
  // Initialisation de l'historique
  for (int i = 0; i < HISTORIQUE_SIZE; i++) {
    historiqueDistance[i] = 0;
    historiquePoids[i] = 0;
  }
}

void loop() {
  // Vérification WiFi
  if (WiFi.status() != WL_CONNECTED) {
    connectToWiFi();
  }
  
  // Lecture des capteurs
  if (millis() - lastSensorRead > sensorInterval) {
    lireTousLesCapteursESP32();
    lastSensorRead = millis();
  }
  
  // Envoi au serveur
  if (millis() - lastServerUpdate > serverInterval) {
    envoyerDonneesServeur();
    lastServerUpdate = millis();
  }
  
  // Gestion des boutons
  gererBoutons();
  
  delay(100);
}

void lireTousLesCapteursESP32() {
  // 1. Lecture RFID
  lireRFID();
  
  // 2. Lecture capteur de poids
  lirePoids();
  
  // 3. Lecture capteur ultrasonique
  lireDistance();
  
  // 4. Mise à jour des LEDs
  mettreAJourLEDs();
  
  // 5. Affichage debug
  afficherDonneesCapteurs();
}

void lireRFID() {
  rfidDetecte = false;
  dernierTagRFID = "";
  
  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    return;
  }
  
  rfidDetecte = true;
  
  // Lecture de l'UID
  String tagUID = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    tagUID += String(mfrc522.uid.uidByte[i] < 0x10 ? "0" : "");
    tagUID += String(mfrc522.uid.uidByte[i], HEX);
  }
  tagUID.toUpperCase();
  
  dernierTagRFID = tagUID;
  
  // Arrêt de la communication avec la carte
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  
  Serial.println("RFID détecté: " + tagUID);
}

void lirePoids() {
  if (scale.is_ready()) {
    float lecture = scale.get_units(3); // Moyenne de 3 lectures
    poidsActuel = lecture * facteurCalibragePoids;
    
    // Ajout à l'historique pour stabilité
    historiquePoids[indexHistorique] = poidsActuel;
    
    // Calcul de la moyenne mobile
    float somme = 0;
    for (int i = 0; i < HISTORIQUE_SIZE; i++) {
      somme += historiquePoids[i];
    }
    poidsActuel = somme / HISTORIQUE_SIZE;
  } else {
    Serial.println("Capteur de poids non prêt");
  }
}

void lireDistance() {
  int distance = sonar.ping_cm();
  
  if (distance > 0) {
    distanceActuelle = distance;
    
    // Ajout à l'historique
    historiqueDistance[indexHistorique] = distanceActuelle;
    
    // Calcul de la moyenne mobile
    float somme = 0;
    int count = 0;
    for (int i = 0; i < HISTORIQUE_SIZE; i++) {
      if (historiqueDistance[i] > 0) {
        somme += historiqueDistance[i];
        count++;
      }
    }
    if (count > 0) {
      distanceActuelle = somme / count;
    }
  }
  
  // Mise à jour de l'index d'historique
  indexHistorique = (indexHistorique + 1) % HISTORIQUE_SIZE;
}

void envoyerDonneesServeur() {
  if (WiFi.status() != WL_CONNECTED) return;
  
  HTTPClient http;
  http.begin(String(serverURL) + "/equipement/sensor-data/" + String(equipementId));
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key", apiKey);
  
  // Création du JSON avec toutes les données capteurs
  DynamicJsonDocument doc(1024);
  doc["rfid_detected"] = rfidDetecte;
  doc["rfid_tag"] = dernierTagRFID;
  doc["weight"] = poidsActuel;
  doc["distance"] = distanceActuelle;
  doc["timestamp"] = millis();
  doc["equipment_id"] = equipementId;
  
  // Données de référence pour comparaison
  doc["reference_data"]["rfid_tag"] = rfidTagReference;
  doc["reference_data"]["weight"] = poidsReference;
  doc["reference_data"]["distance"] = distanceReference;
  
  // Métadonnées
  doc["sensor_status"]["rfid_reader"] = mfrc522.PCD_PerformSelfTest();
  doc["sensor_status"]["weight_sensor"] = scale.is_ready();
  doc["sensor_status"]["ultrasonic"] = (distanceActuelle > 0);
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  int httpResponseCode = http.POST(jsonString);
  
  if (httpResponseCode > 0) {
    String response = http.getString();
    
    // Traitement de la réponse
    DynamicJsonDocument responseDoc(1024);
    deserializeJson(responseDoc, response);
    
    if (responseDoc["success"]) {
      Serial.println("✓ Données envoyées avec succès");
      
      // Mise à jour des LEDs selon la réponse du serveur
      if (responseDoc["detection_result"]) {
        traiterReponseServeur(responseDoc["detection_result"]);
      }
    } else {
      Serial.println("✗ Erreur serveur: " + String(responseDoc["error"].as<String>()));
      signalError();
    }
  } else {
    Serial.println("✗ Erreur HTTP: " + String(httpResponseCode));
    signalError();
  }
  
  http.end();
}

void traiterReponseServeur(JsonObject detectionResult) {
  bool physicallyPresent = detectionResult["physically_present"];
  int confidenceScore = detectionResult["confidence_score"];
  String suggestedState = detectionResult["suggested_state"];
  
  Serial.println("Résultat détection:");
  Serial.println("- Présent: " + String(physicallyPresent ? "OUI" : "NON"));
  Serial.println("- Confiance: " + String(confidenceScore) + "%");
  Serial.println("- État suggéré: " + suggestedState);
  
  // Signal sonore selon le niveau de confiance
  if (confidenceScore >= 80) {
    tone(BUZZER, 1500, 100); // Son aigu pour haute confiance
  } else if (confidenceScore >= 50) {
    tone(BUZZER, 1000, 150); // Son moyen pour confiance moyenne
  } else {
    tone(BUZZER, 500, 200);  // Son grave pour faible confiance
  }
}

void mettreAJourLEDs() {
  // Éteindre toutes les LEDs
  digitalWrite(LED_DISPONIBLE, LOW);
  digitalWrite(LED_RESERVE, LOW);
  digitalWrite(LED_MAINTENANCE, LOW);
  digitalWrite(LED_ABSENT, LOW);
  
  // Logique locale simple (sera affinée par le serveur)
  bool rfidMatch = (dernierTagRFID == rfidTagReference && rfidDetecte);
  bool poidsMatch = (abs(poidsActuel - poidsReference) < (poidsReference * 0.1));
  bool distanceMatch = (abs(distanceActuelle - distanceReference) < 10);
  
  int matches = 0;
  if (rfidMatch) matches++;
  if (poidsMatch) matches++;
  if (distanceMatch) matches++;
  
  if (matches >= 2) {
    digitalWrite(LED_DISPONIBLE, HIGH); // Probablement disponible
  } else if (matches == 1) {
    digitalWrite(LED_MAINTENANCE, HIGH); // Incertain
  } else {
    digitalWrite(LED_ABSENT, HIGH); // Probablement absent
  }
}

void gererBoutons() {
  // Bouton de calibrage
  if (digitalRead(BUTTON_CALIBRATE) == LOW) {
    delay(50); // Debounce
    if (digitalRead(BUTTON_CALIBRATE) == LOW) {
      demarrerCalibrage();
      while (digitalRead(BUTTON_CALIBRATE) == LOW) delay(10);
    }
  }
  
  // Bouton de reset
  if (digitalRead(BUTTON_RESET) == LOW) {
    delay(50); // Debounce
    if (digitalRead(BUTTON_RESET) == LOW) {
      resetEquipement();
      while (digitalRead(BUTTON_RESET) == LOW) delay(10);
    }
  }
}

void demarrerCalibrage() {
  Serial.println("=== DÉBUT DU CALIBRAGE ===");
  modeCalibrage = true;
  
  // Signal de calibrage
  for (int i = 0; i < 3; i++) {
    digitalWrite(LED_MAINTENANCE, HIGH);
    tone(BUZZER, 2000, 200);
    delay(300);
    digitalWrite(LED_MAINTENANCE, LOW);
    delay(200);
  }
  
  Serial.println("1. Placez l'équipement sur le capteur");
  Serial.println("2. Présentez le tag RFID");
  Serial.println("3. Attendez 10 secondes...");
  
  // Période de calibrage de 10 secondes
  unsigned long startCalibrage = millis();
  String tagCalibrage = "";
  float poidsCalibrage = 0;
  int distanceCalibrage = 0;
  int lectures = 0;
  
  while (millis() - startCalibrage < 10000) {
    // Lecture RFID
    if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
      String tagUID = "";
      for (byte i = 0; i < mfrc522.uid.size; i++) {
        tagUID += String(mfrc522.uid.uidByte[i] < 0x10 ? "0" : "");
        tagUID += String(mfrc522.uid.uidByte[i], HEX);
      }
      tagUID.toUpperCase();
      tagCalibrage = tagUID;
      mfrc522.PICC_HaltA();
      mfrc522.PCD_StopCrypto1();
    }
    
    // Lecture poids et distance
    if (scale.is_ready()) {
      poidsCalibrage += scale.get_units();
      lectures++;
    }
    
    int dist = sonar.ping_cm();
    if (dist > 0) {
      distanceCalibrage += dist;
    }
    
    delay(100);
  }
  
  // Calcul des moyennes
  if (lectures > 0) {
    poidsCalibrage /= lectures;
  }
  distanceCalibrage /= 100; // Moyenne sur ~100 lectures
  
  // Sauvegarde des valeurs de référence
  if (tagCalibrage != "") {
    rfidTagReference = tagCalibrage;
  }
  if (poidsCalibrage > 0) {
    poidsReference = poidsCalibrage;
  }
  if (distanceCalibrage > 0) {
    distanceReference = distanceCalibrage;
  }
  
  sauvegarderConfiguration();
  envoyerCalibrageServeur();
  
  Serial.println("=== CALIBRAGE TERMINÉ ===");
  Serial.println("RFID: " + rfidTagReference);
  Serial.println("Poids: " + String(poidsReference) + "g");
  Serial.println("Distance: " + String(distanceReference) + "cm");
  
  // Signal de fin de calibrage
  for (int i = 0; i < 5; i++) {
    digitalWrite(LED_DISPONIBLE, HIGH);
    tone(BUZZER, 1500, 100);
    delay(150);
    digitalWrite(LED_DISPONIBLE, LOW);
    delay(100);
  }
  
  modeCalibrage = false;
}

void envoyerCalibrageServeur() {
  HTTPClient http;
  http.begin(String(serverURL) + "/equipement/calibrate/" + String(equipementId));
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key", apiKey);
  
  DynamicJsonDocument doc(512);
  doc["rfid_tag"] = rfidTagReference;
  doc["reference_weight"] = poidsReference;
  doc["reference_distance"] = distanceReference;
  doc["calibration_timestamp"] = millis();
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  int httpResponseCode = http.POST(jsonString);
  
  if (httpResponseCode == 200) {
    Serial.println("✓ Calibrage envoyé au serveur");
  } else {
    Serial.println("✗ Erreur envoi calibrage: " + String(httpResponseCode));
  }
  
  http.end();
}

void sauvegarderConfiguration() {
  // Sauvegarde en EEPROM
  EEPROM.writeInt(0, equipementId);
  EEPROM.writeString(10, rfidTagReference);
  EEPROM.writeFloat(50, poidsReference);
  EEPROM.writeInt(60, distanceReference);
  EEPROM.commit();
}

void chargerConfiguration() {
  // Chargement depuis EEPROM
  equipementId = EEPROM.readInt(0);
  if (equipementId == 0 || equipementId == -1) {
    equipementId = 1; // Valeur par défaut
  }
  
  rfidTagReference = EEPROM.readString(10);
  poidsReference = EEPROM.readFloat(50);
  distanceReference = EEPROM.readInt(60);
}

void connectToWiFi() {
  Serial.println("Connexion au WiFi...");
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ WiFi connecté!");
    Serial.println("IP: " + WiFi.localIP().toString());
    signalConnection();
  } else {
    Serial.println("\n✗ Échec connexion WiFi");
    signalError();
  }
}

void afficherDonneesCapteurs() {
  Serial.println("--- Données Capteurs ---");
  Serial.println("RFID: " + (rfidDetecte ? "✓ " + dernierTagRFID : "✗ Aucun"));
  Serial.println("Poids: " + String(poidsActuel, 1) + "g (ref: " + String(poidsReference, 1) + "g)");
  Serial.println("Distance: " + String(distanceActuelle) + "cm (ref: " + String(distanceReference) + "cm)");
  Serial.println("WiFi: " + String(WiFi.status() == WL_CONNECTED ? "✓" : "✗"));
  Serial.println("------------------------");
}

// Fonctions de signalisation
void signalStartup() {
  for (int i = 0; i < 4; i++) {
    digitalWrite(LED_DISPONIBLE + i, HIGH);
    delay(200);
  }
  for (int i = 0; i < 4; i++) {
    digitalWrite(LED_DISPONIBLE + i, LOW);
    delay(100);
  }
  tone(BUZZER, 1000, 500);
}

void signalConnection() {
  digitalWrite(LED_DISPONIBLE, HIGH);
  tone(BUZZER, 1500, 200);
  delay(300);
  digitalWrite(LED_DISPONIBLE, LOW);
}

void signalError() {
  for (int i = 0; i < 3; i++) {
    digitalWrite(LED_MAINTENANCE, HIGH);
    tone(BUZZER, 500, 200);
    delay(250);
    digitalWrite(LED_MAINTENANCE, LOW);
    delay(250);
  }
}

void resetEquipement() {
  Serial.println("=== RESET ÉQUIPEMENT ===");
  signalStartup();
  
  // Reset des valeurs
  dernierTagRFID = "";
  rfidDetecte = false;
  poidsActuel = 0;
  distanceActuelle = 0;
  
  // Forcer une lecture et envoi
  lireTousLesCapteursESP32();
  envoyerDonneesServeur();
  
  Serial.println("Reset terminé");
}