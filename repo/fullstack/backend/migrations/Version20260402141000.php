<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Foundation schema with reference, auth, and base domain tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS firms (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(500) DEFAULT NULL, status VARCHAR(32) NOT NULL DEFAULT "ACTIVE", created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FIRMS_NAME (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS locations (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(500) DEFAULT NULL, capacity INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_LOCATIONS_NAME (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS org_units (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_ORG_UNITS_PARENT (parent_id), PRIMARY KEY(id), CONSTRAINT FK_ORG_UNITS_PARENT FOREIGN KEY (parent_id) REFERENCES org_units (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS question_categories (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_QUESTION_CATEGORIES_PARENT (parent_id), UNIQUE INDEX UNIQ_QUESTION_CATEGORIES_NAME (name), PRIMARY KEY(id), CONSTRAINT FK_QUESTION_CATEGORIES_PARENT FOREIGN KEY (parent_id) REFERENCES question_categories (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS question_tags (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_QUESTION_TAGS_NAME (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT "ACTIVE", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_USERS_USERNAME (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS system_settings (id INT AUTO_INCREMENT NOT NULL, setting_key VARCHAR(120) NOT NULL, setting_value LONGTEXT NOT NULL, updated_by INT DEFAULT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SETTINGS_KEY (setting_key), INDEX IDX_SETTINGS_UPDATED_BY (updated_by), PRIMARY KEY(id), CONSTRAINT FK_SETTINGS_UPDATED_BY FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, username VARCHAR(180) NOT NULL, attempt_at DATETIME NOT NULL, success TINYINT(1) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, INDEX IDX_LOGIN_ATTEMPTS_USER (user_id), PRIMARY KEY(id), CONSTRAINT FK_LOGIN_ATTEMPTS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS account_lockouts (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, failed_count INT NOT NULL DEFAULT 0, locked_until DATETIME DEFAULT NULL, captcha_required TINYINT(1) NOT NULL DEFAULT 0, UNIQUE INDEX UNIQ_LOCKOUT_USER (user_id), PRIMARY KEY(id), CONSTRAINT FK_LOCKOUT_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS captcha_challenges (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(128) NOT NULL, challenge_payload JSON NOT NULL, expected_answer_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_CAPTCHA_TOKEN (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS password_reset_requests (id INT AUTO_INCREMENT NOT NULL, target_user_id INT NOT NULL, initiated_by_admin_id INT NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, INDEX IDX_RESET_TARGET_USER (target_user_id), INDEX IDX_RESET_ADMIN_USER (initiated_by_admin_id), PRIMARY KEY(id), CONSTRAINT FK_RESET_TARGET_USER FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE CASCADE, CONSTRAINT FK_RESET_ADMIN_USER FOREIGN KEY (initiated_by_admin_id) REFERENCES users (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE IF NOT EXISTS practitioners (id INT AUTO_INCREMENT NOT NULL, firm_id INT NOT NULL, full_name VARCHAR(255) NOT NULL, license_number_encrypted TEXT NOT NULL, license_jurisdiction VARCHAR(120) NOT NULL, contact_email VARCHAR(255) DEFAULT NULL, contact_phone VARCHAR(80) DEFAULT NULL, status VARCHAR(32) NOT NULL DEFAULT "ACTIVE", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_PRACTITIONERS_FIRM (firm_id), PRIMARY KEY(id), CONSTRAINT FK_PRACTITIONERS_FIRM FOREIGN KEY (firm_id) REFERENCES firms (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS appointments (id INT AUTO_INCREMENT NOT NULL, practitioner_id INT NOT NULL, location_id INT NOT NULL, slot_id INT DEFAULT NULL, booked_by INT DEFAULT NULL, state VARCHAR(32) NOT NULL, held_until DATETIME DEFAULT NULL, reschedule_count INT NOT NULL DEFAULT 0, booked_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, INDEX IDX_APPOINTMENTS_PRACTITIONER (practitioner_id), INDEX IDX_APPOINTMENTS_LOCATION (location_id), INDEX IDX_APPOINTMENTS_BOOKED_BY (booked_by), PRIMARY KEY(id), CONSTRAINT FK_APPOINTMENTS_PRACTITIONER FOREIGN KEY (practitioner_id) REFERENCES practitioners (id), CONSTRAINT FK_APPOINTMENTS_LOCATION FOREIGN KEY (location_id) REFERENCES locations (id), CONSTRAINT FK_APPOINTMENTS_BOOKED_BY FOREIGN KEY (booked_by) REFERENCES users (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS questions (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, status VARCHAR(32) NOT NULL DEFAULT "DRAFT", created_by INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_QUESTIONS_CATEGORY (category_id), INDEX IDX_QUESTIONS_CREATED_BY (created_by), PRIMARY KEY(id), CONSTRAINT FK_QUESTIONS_CATEGORY FOREIGN KEY (category_id) REFERENCES question_categories (id), CONSTRAINT FK_QUESTIONS_CREATED_BY FOREIGN KEY (created_by) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS questions');
        $this->addSql('DROP TABLE IF EXISTS appointments');
        $this->addSql('DROP TABLE IF EXISTS practitioners');
        $this->addSql('DROP TABLE IF EXISTS password_reset_requests');
        $this->addSql('DROP TABLE IF EXISTS captcha_challenges');
        $this->addSql('DROP TABLE IF EXISTS account_lockouts');
        $this->addSql('DROP TABLE IF EXISTS login_attempts');
        $this->addSql('DROP TABLE IF EXISTS system_settings');
        $this->addSql('DROP TABLE IF EXISTS users');
        $this->addSql('DROP TABLE IF EXISTS question_tags');
        $this->addSql('DROP TABLE IF EXISTS question_categories');
        $this->addSql('DROP TABLE IF EXISTS org_units');
        $this->addSql('DROP TABLE IF EXISTS locations');
        $this->addSql('DROP TABLE IF EXISTS firms');
    }
}
