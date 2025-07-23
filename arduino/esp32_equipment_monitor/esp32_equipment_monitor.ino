#include <WiFi.h>
#include <HX711.h>
#include <SPI.h>
#include <MFRC522.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// === CONFIGURATION WIFI ===
const char* ssid = "Kalumba";
const char* password = "P@55word1234";

// === HX711 (Capteur de poids) ===
#define HX711_DT 4   // GPIO4
#define HX711_SCK 5  // GPIO5
HX711 balance;
float facteurCalibration = 1.0; // À ajuster après calibration

// === RFID RC522 ===
#define SS_PIN 21   // GPIO21
#define RST_PIN 22  // GPIO22
MFRC522 mfrc522(SS_PIN, RST_PIN);  // SDA, RST

// === Capteur Ultrason HC-SR04 ===
#define trigPin1 12  // GPIO12
#define echoPin1 14  // GPIO14
#define trigPin2 27  // GPIO27
#define echoPin2 26  // GPIO26

// === Buzzer, LED, Relais ===
#define buzzerPin 2     // GPIO2
#define ledPin 13       // GPIO13
#define relaisPin 15    // GPIO15

extern int equipementId; // Assure que la variable est globale

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("Initialisation...");

  // === Capteur de poids ===
  Serial.println("Initialisation du capteur de poids...");
  balance.begin(HX711_DT, HX711_SCK);
  if (!balance.is_ready()) {
    Serial.println("Erreur : HX711 non détecté !");
  } else {
    Serial.println("HX711 prêt.");
  }

  // === RFID ===
  Serial.println("Initialisation du lecteur RFID...");
  SPI.begin();  // SCK=18, MISO=19, MOSI=23 (par défaut sur ESP32)
  mfrc522.PCD_Init();
  delay(50);
  Serial.println("RFID initialisé.");

  // === Capteur Ultrason ===
  pinMode(trigPin1, OUTPUT);
  pinMode(echoPin1, INPUT);
  pinMode(trigPin2, OUTPUT);
  pinMode(echoPin2, INPUT);

  // === Périphériques ===
  pinMode(buzzerPin, OUTPUT);
  pinMode(ledPin, OUTPUT);
  pinMode(relaisPin, OUTPUT);

  digitalWrite(buzzerPin, LOW);
  digitalWrite(ledPin, LOW);
  digitalWrite(relaisPin, LOW);

  // === WiFi ===
  Serial.print("Connexion au WiFi");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nConnecté au WiFi !");
  Serial.print("Adresse IP : ");
  Serial.println(WiFi.localIP());
}

void envoyerDonneesPourTousEquipements(float poids, float distance1, float distance2, String rfidTag) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = "http://172.29.16.201:8000/api/equipements/all";
    http.begin(url);
    int httpCode = http.GET();
    if (httpCode > 0) {
      String payload = http.getString();
      DynamicJsonDocument doc(4096);
      DeserializationError error = deserializeJson(doc, payload);
      if (!error) {
        for (JsonObject eq : doc.as<JsonArray>()) {
          int id = eq["id"];
          envoyerDonneesVersAPI(id, rfidTag, poids, distance1, distance2);
        }
      } else {
        Serial.println("❌ Erreur de parsing JSON équipements");
      }
    } else {
      Serial.println("❌ Erreur HTTP lors de la récupération des équipements");
    }
    http.end();
  } else {
    Serial.println("❌ WiFi non connecté !");
  }
}

void envoyerDonneesVersAPI(int equipementId, String rfidTag, float poids, float distance1, float distance2) {
  if ((WiFi.status() == WL_CONNECTED)) {
    HTTPClient http;
    String url = "http://172.29.16.201:8000/api/equipement/sensor-data/" + String(equipementId);
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    if (rfidTag == "" || rfidTag == "00000000") rfidTag = "N/A";
    if (isnan(poids)) poids = 0.0;
    if (isnan(distance1)) distance1 = 0.0;
    if (isnan(distance2)) distance2 = 0.0;
    String json = "{";
    json += "\"rfid_tag\":\"" + rfidTag + "\",";
    json += "\"weight\":" + String(poids, 2) + ",";
    json += "\"distance1\":" + String(distance1, 2) + ",";
    json += "\"distance2\":" + String(distance2, 2);
    json += "}";
    Serial.print("JSON envoyé : ");
    Serial.println(json);
    int httpResponseCode = http.POST(json);
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.print(" Données envoyées pour équipement ");
      Serial.print(equipementId);

    } else {
      Serial.print(" Erreur HTTP pour équipement ");
      Serial.print(equipementId);

      http.end();
      return;
    }
    http.end();
  } else {
    Serial.println(" WiFi non connecté !");
  }
}

void loop() {
  // === Mesure distance 1 ===
  digitalWrite(trigPin1, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin1, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin1, LOW);
  long duration1 = pulseIn(echoPin1, HIGH);
  float distance1 = duration1 * 0.034 / 2;
  Serial.print("Distance 1 : ");
  Serial.print(distance1, 2);
  Serial.println(" cm");

  // === Mesure distance 2 ===
  digitalWrite(trigPin2, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin2, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin2, LOW);
  long duration2 = pulseIn(echoPin2, HIGH);
  float distance2 = duration2 * 0.034 / 2;
  Serial.print("Distance 2 : ");
  Serial.print(distance2, 2);
  Serial.println(" cm");

  // === Poids ===
  float poids = 0;
  bool poidsPret = balance.is_ready();
  if (poidsPret) {
    poids = balance.get_units(10) * facteurCalibration;
    Serial.print("Poids calibré : ");
    Serial.print(poids, 2);
    Serial.println("g");
  } else {
    Serial.println("Capteur de poids non prêt !");
  }

  // Envoi des données à l'API sans RFID
  String rfidTag = "N/A"; // RFID désactivé
  envoyerDonneesPourTousEquipements(poids, distance1, distance2, rfidTag);

  delay(5000);  // Pause avant prochaine mesure
}
