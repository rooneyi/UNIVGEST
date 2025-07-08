<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter les champs de capteurs à l'entité Equipement
 */
final class Version20250107000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs pour les capteurs ESP32 (RFID, poids, distance)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipement ADD rfid_tag VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement ADD poids_reference DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement ADD poids_actuel DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement ADD distance_reference INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement ADD distance_actuelle INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement ADD derniere_mise_a_jour TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement ADD donnees_capteurs_historique JSON DEFAULT NULL');
        
        $this->addSql('COMMENT ON COLUMN equipement.rfid_tag IS \'Tag RFID unique de l\'équipement\'');
        $this->addSql('COMMENT ON COLUMN equipement.poids_reference IS \'Poids de référence en grammes\'');
        $this->addSql('COMMENT ON COLUMN equipement.poids_actuel IS \'Poids actuel mesuré en grammes\'');
        $this->addSql('COMMENT ON COLUMN equipement.distance_reference IS \'Distance de référence en centimètres\'');
        $this->addSql('COMMENT ON COLUMN equipement.distance_actuelle IS \'Distance actuelle mesurée en centimètres\'');
        $this->addSql('COMMENT ON COLUMN equipement.derniere_mise_a_jour IS \'Dernière mise à jour des données capteurs\'');
        $this->addSql('COMMENT ON COLUMN equipement.donnees_capteurs_historique IS \'Historique des données capteurs (JSON)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipement DROP rfid_tag');
        $this->addSql('ALTER TABLE equipement DROP poids_reference');
        $this->addSql('ALTER TABLE equipement DROP poids_actuel');
        $this->addSql('ALTER TABLE equipement DROP distance_reference');
        $this->addSql('ALTER TABLE equipement DROP distance_actuelle');
        $this->addSql('ALTER TABLE equipement DROP derniere_mise_a_jour');
        $this->addSql('ALTER TABLE equipement DROP donnees_capteurs_historique');
    }
}