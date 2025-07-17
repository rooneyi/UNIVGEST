#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <EEPROM.h>

// Configuration WiFi
const char* ssid = "kalumba";
const char* password = "P@55word";

// Configuration serveur
const char* serverURL = "http://localhost:8000/api";

// Pins
const int LED_DISPONIBLE = 2;    // LED verte
const int LED_RESERVE = 4;       // LED rouge
const int LED_MAINTENANCE = 5;   // LED orange
const int BUZZER = 18;
const int BUTTON_RESET = 19;
const int SENSOR_PIN = 21;       // Capteur de présence

// Variables
int equipementId = 1;            // ID de l'équipement (à configurer)
bool lastConnectionState = false;
bool equipementConnected = false;
unsigned long lastUpdate = 0;
const unsigned long updateInterval = 5000; // 5 secondes

void setup() {
  Serial.begin(115200);

  // Configuration des pins
  pinMode(LED_DISPONIBLE, OUTPUT);
  pinMode(LED_RESERVE, OUTPUT);
  pinMode(LED_MAINTENANCE, OUTPUT);
  pinMode(BUZZER, OUTPUT);
  pinMode(BUTTON_RESET, INPUT_PULLUP);
  pinMode(SENSOR_PIN, INPUT);

  // Initialisation EEPROM
  EEPROM.begin(512);

  // Connexion WiFi
  connectToWiFi();

  // Signal de démarrage
  signalStartup();

  Serial.println("ESP32 Equipment Monitor démarré");
  Serial.println("ID Équipement: " + String(equipementId));
}

void loop() {
  // Vérification de la connexion WiFi
  if (WiFi.status() != WL_CONNECTED) {
    connectToWiFi();
  }

  // Lecture du capteur de présence
  bool currentConnectionState = digitalRead(SENSOR_PIN);

  // Détection de changement d'état
  if (currentConnectionState != lastConnectionState) {
    equipementConnected = currentConnectionState;
    lastConnectionState = currentConnectionState;

    // Envoi immédiat de la mise à jour
    sendStatusUpdate();

    // Signal sonore pour changement d'état
    if (equipementConnected) {
      signalConnection();
    } else {
      signalDisconnection();
    }
  }

  // Mise à jour périodique
  if (millis() - lastUpdate > updateInterval) {
    checkEquipmentStatus();
    lastUpdate = millis();
  }

  // Vérification du bouton reset
  if (digitalRead(BUTTON_RESET) == LOW) {
    delay(50); // Debounce
    if (digitalRead(BUTTON_RESET) == LOW) {
      resetEquipment();
      delay(1000); // Éviter les répétitions
    }
  }

  delay(100);
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
    Serial.println("\nWiFi connecté!");
    Serial.println("Adresse IP: " + WiFi.localIP().toString());
    digitalWrite(LED_DISPONIBLE, HIGH);
    delay(500);
    digitalWrite(LED_DISPONIBLE, LOW);
  } else {
    Serial.println("\nÉchec de connexion WiFi");
    signalError();
  }
}

void sendStatusUpdate() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[DEBUG] WiFi non connecté, envoi annulé.");
    return;
  }
  HTTPClient http;
  String url = String(serverURL) + "/equipement/sensor-data/" + String(equipementId);
  Serial.println("[DEBUG] POST vers: " + url);
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  // Création du JSON
  DynamicJsonDocument doc(200);
  doc["connected"] = equipementConnected;
  doc["timestamp"] = millis();
  String jsonString;
  serializeJson(doc, jsonString);
  Serial.println("[DEBUG] Données envoyées: " + jsonString);
  int httpResponseCode = http.POST(jsonString);
  Serial.println("[DEBUG] Code réponse HTTP: " + String(httpResponseCode));
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("[DEBUG] Réponse serveur: " + response);
    // Analyse de la réponse
    DynamicJsonDocument responseDoc(500);
    deserializeJson(responseDoc, response);
    if (responseDoc["success"]) {
      updateLEDs(responseDoc["equipement"]);
    }
  } else {
    Serial.println("[DEBUG] Erreur HTTP: " + String(httpResponseCode));
    signalError();
  }
  http.end();
}

void checkEquipmentStatus() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[DEBUG] WiFi non connecté, vérification annulée.");
    return;
  }
  HTTPClient http;
  String url = String(serverURL) + "/equipement/status/" + String(equipementId);
  Serial.println("[DEBUG] GET vers: " + url);
  http.begin(url);
  int httpResponseCode = http.GET();
  Serial.println("[DEBUG] Code réponse HTTP: " + String(httpResponseCode));
  if (httpResponseCode == 200) {
    String response = http.getString();
    Serial.println("[DEBUG] Réponse serveur: " + response);
    DynamicJsonDocument doc(500);
    deserializeJson(doc, response);
    updateLEDs(doc.as<JsonObject>());
  } else {
    Serial.println("[DEBUG] Erreur lors de la vérification du statut: " + String(httpResponseCode));
    signalError();
  }
  http.end();
}

void updateLEDs(JsonObject equipement) {
  // Éteindre toutes les LEDs
  digitalWrite(LED_DISPONIBLE, LOW);
  digitalWrite(LED_RESERVE, LOW);
  digitalWrite(LED_MAINTENANCE, LOW);

  bool disponible = equipement["disponible"];
  String etat = equipement["etat"];
  bool reservationActive = !equipement["reservation_active"].isNull();

  if (!equipementConnected) {
    // Équipement déconnecté - LED maintenance clignotante
    blinkLED(LED_MAINTENANCE, 3);
  } else if (reservationActive) {
    // Équipement réservé - LED rouge
    digitalWrite(LED_RESERVE, HIGH);
  } else if (disponible && etat == "disponible") {
    // Équipement disponible - LED verte
    digitalWrite(LED_DISPONIBLE, HIGH);
  } else {
    // Équipement en maintenance - LED orange
    digitalWrite(LED_MAINTENANCE, HIGH);
  }
}

void blinkLED(int pin, int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(pin, HIGH);
    delay(200);
    digitalWrite(pin, LOW);
    delay(200);
  }
}

void signalStartup() {
  // Séquence de démarrage
  digitalWrite(LED_DISPONIBLE, HIGH);
  delay(300);
  digitalWrite(LED_RESERVE, HIGH);
  delay(300);
  digitalWrite(LED_MAINTENANCE, HIGH);
  delay(300);

  // Éteindre toutes les LEDs
  digitalWrite(LED_DISPONIBLE, LOW);
  digitalWrite(LED_RESERVE, LOW);
  digitalWrite(LED_MAINTENANCE, LOW);

  // Signal sonore
  tone(BUZZER, 1000, 200);
  delay(300);
  tone(BUZZER, 1500, 200);
}

void signalConnection() {
  // Signal de connexion d'équipement
  blinkLED(LED_DISPONIBLE, 2);
  tone(BUZZER, 1200, 100);
  delay(150);
  tone(BUZZER, 1200, 100);
}

void signalDisconnection() {
  // Signal de déconnexion d'équipement
  blinkLED(LED_RESERVE, 3);
  tone(BUZZER, 800, 300);
}

void signalError() {
  // Signal d'erreur
  for (int i = 0; i < 5; i++) {
    digitalWrite(LED_RESERVE, HIGH);
    tone(BUZZER, 500, 100);
    delay(100);
    digitalWrite(LED_RESERVE, LOW);
    delay(100);
  }
}

void resetEquipment() {
  Serial.println("Reset de l'équipement...");

  // Signal de reset
  signalStartup();

  // Forcer une mise à jour
  equipementConnected = digitalRead(SENSOR_PIN);
  sendStatusUpdate();

  Serial.println("Reset terminé");
}
