// Monitoring ESP32 en temps réel
class ESP32Monitor {
    constructor() {
        this.equipements = new Map();
        this.updateInterval = 5000; // 5 secondes
        this.init();
    }

    init() {
        this.loadEquipements();
        this.startMonitoring();
        this.setupEventListeners();
    }

    async loadEquipements() {
        try {
            const response = await fetch('/api/equipements/all');
            const data = await response.json();

            data.forEach(equipement => {
                this.equipements.set(equipement.id, equipement);
            });

            this.updateUI();
        } catch (error) {
            console.error('Erreur lors du chargement des équipements:', error);
        }
    }

    async checkEquipementStatus(id) {
        try {
            const response = await fetch(`/api/equipement/status/${id}`);
            const data = await response.json();

            if (response.ok) {
                this.equipements.set(id, data);
                this.updateEquipementUI(data);
            }
        } catch (error) {
            console.error(`Erreur lors de la vérification de l'équipement ${id}:`, error);
        }
    }

    startMonitoring() {
        setInterval(() => {
            this.equipements.forEach((equipement, id) => {
                this.checkEquipementStatus(id);
            });
        }, this.updateInterval);
    }

    updateUI() {
        this.equipements.forEach(equipement => {
            this.updateEquipementUI(equipement);
        });
    }

    updateEquipementUI(equipement) {
        const statusElement = document.querySelector(`[data-equipement-id="${equipement.id}"]`);
        if (statusElement) {
            const statusBadge = statusElement.querySelector('.status-badge');
            const actionButton = statusElement.querySelector('.action-button');

            if (statusBadge) {
                statusBadge.className = `status-badge ${this.getStatusClass(equipement)}`;
                statusBadge.textContent = this.getStatusText(equipement);

                // Ajout d'informations détaillées
                const detailsElement = statusElement.querySelector('.sensor-details');
                if (detailsElement) {
                    this.updateSensorDetails(detailsElement, equipement);
                }
            }

            if (actionButton) {
                actionButton.disabled = !equipement.disponible;
                actionButton.textContent = equipement.disponible ? 'Réserver' : 'Indisponible';

                // Ajout d'une classe pour l'état physique
                if (equipement.physically_present === false) {
                    actionButton.classList.add('physically-absent');
                    actionButton.title = 'Équipement physiquement absent';
                } else {
                    actionButton.classList.remove('physically-absent');
                    actionButton.title = '';
                }
            }
        }
    }

    updateSensorDetails(detailsElement, equipement) {
        // Utilise directement les propriétés reçues de l'API
        const poids = equipement.poids;
        const distance1 = equipement.distance1;
        const distance2 = equipement.distance2;
        const rfid_tag = equipement.rfid_tag;
        const lastUpdate = equipement.last_update;

        detailsElement.innerHTML = `
            <div class="sensor-info">
                <small class="text-gray-500">
                    ${poids !== undefined ? `Poids: ${poids}g` : ''}
                    ${distance1 !== undefined ? ` | Distance1: ${distance1}cm` : ''}
                    ${distance2 !== undefined ? ` | Distance2: ${distance2}cm` : ''}
                    ${rfid_tag ? ` | RFID: ${rfid_tag}` : ''}
                    ${lastUpdate ? ` | MAJ: ${new Date(lastUpdate).toLocaleTimeString()}` : ''}
                </small>
            </div>
        `;
    }

    getStatusClass(equipement) {
        if (!equipement.disponible) return 'status-unavailable';
        if (equipement.reservation_active) return 'status-reserved';
        return 'status-available';
    }

    getStatusText(equipement) {
        if (!equipement.disponible) return 'Indisponible';
        if (equipement.reservation_active) return 'Réservé';
        return 'Disponible';
    }

    setupEventListeners() {
        // WebSocket pour les mises à jour en temps réel (optionnel)
        if (window.WebSocket) {
            this.setupWebSocket();
        }
    }

    setupWebSocket() {
        // Configuration WebSocket pour les mises à jour instantanées
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws/equipements`;

        try {
            this.ws = new WebSocket(wsUrl);

            this.ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                if (data.type === 'equipement_update') {
                    this.equipements.set(data.equipement.id, data.equipement);
                    this.updateEquipementUI(data.equipement);
                }
            };

            this.ws.onerror = () => {
                console.log('WebSocket non disponible, utilisation du polling');
            };
        } catch (error) {
            console.log('WebSocket non supporté, utilisation du polling');
        }
    }
}

// Initialisation automatique
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-equipement-id]')) {
        new ESP32Monitor();
    }
});
