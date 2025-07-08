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
            }
            
            if (actionButton) {
                actionButton.disabled = !equipement.disponible;
                actionButton.textContent = equipement.disponible ? 'Réserver' : 'Indisponible';
            }
        }
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