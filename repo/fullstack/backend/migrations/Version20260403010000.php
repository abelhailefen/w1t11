<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module 5 scheduling tables, slot FKs, and location status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE locations ADD status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE'");

        $this->addSql('CREATE TABLE IF NOT EXISTS availability_windows (id INT AUTO_INCREMENT NOT NULL, practitioner_id INT DEFAULT NULL, org_unit_id INT DEFAULT NULL, weekday INT NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, slot_minutes INT NOT NULL DEFAULT 30, INDEX IDX_AVAILABILITY_PRACTITIONER (practitioner_id), INDEX IDX_AVAILABILITY_ORG_UNIT (org_unit_id), PRIMARY KEY(id), CONSTRAINT FK_AVAILABILITY_PRACTITIONER FOREIGN KEY (practitioner_id) REFERENCES practitioners (id) ON DELETE CASCADE, CONSTRAINT FK_AVAILABILITY_ORG_UNIT FOREIGN KEY (org_unit_id) REFERENCES org_units (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("CREATE TABLE IF NOT EXISTS appointment_slots (id INT AUTO_INCREMENT NOT NULL, practitioner_id INT NOT NULL, location_id INT NOT NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, capacity INT NOT NULL DEFAULT 1, available_count INT NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'AVAILABLE', INDEX IDX_APPOINTMENT_SLOTS_PRACTITIONER (practitioner_id), INDEX IDX_APPOINTMENT_SLOTS_LOCATION (location_id), INDEX IDX_APPOINTMENT_SLOTS_START (start_at), UNIQUE INDEX UNIQ_APPOINTMENT_SLOT (practitioner_id, location_id, start_at, end_at), PRIMARY KEY(id), CONSTRAINT FK_APPOINTMENT_SLOTS_PRACTITIONER FOREIGN KEY (practitioner_id) REFERENCES practitioners (id) ON DELETE CASCADE, CONSTRAINT FK_APPOINTMENT_SLOTS_LOCATION FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE TABLE IF NOT EXISTS appointment_reschedule_history (id INT AUTO_INCREMENT NOT NULL, appointment_id INT NOT NULL, old_slot_id INT NOT NULL, new_slot_id INT NOT NULL, changed_by INT NOT NULL, changed_at DATETIME NOT NULL, INDEX IDX_ARH_APPOINTMENT (appointment_id), INDEX IDX_ARH_OLD_SLOT (old_slot_id), INDEX IDX_ARH_NEW_SLOT (new_slot_id), INDEX IDX_ARH_CHANGED_BY (changed_by), PRIMARY KEY(id), CONSTRAINT FK_ARH_APPOINTMENT FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE, CONSTRAINT FK_ARH_OLD_SLOT FOREIGN KEY (old_slot_id) REFERENCES appointment_slots (id), CONSTRAINT FK_ARH_NEW_SLOT FOREIGN KEY (new_slot_id) REFERENCES appointment_slots (id), CONSTRAINT FK_ARH_CHANGED_BY FOREIGN KEY (changed_by) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT FK_APPOINTMENTS_SLOT FOREIGN KEY (slot_id) REFERENCES appointment_slots (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_APPOINTMENTS_SLOT ON appointments (slot_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS appointment_reschedule_history');
        $this->addSql('ALTER TABLE appointments DROP FOREIGN KEY FK_APPOINTMENTS_SLOT');
        $this->addSql('DROP INDEX IDX_APPOINTMENTS_SLOT ON appointments');
        $this->addSql('DROP TABLE IF EXISTS appointment_slots');
        $this->addSql('DROP TABLE IF EXISTS availability_windows');
        $this->addSql('ALTER TABLE locations DROP COLUMN status');
    }
}
