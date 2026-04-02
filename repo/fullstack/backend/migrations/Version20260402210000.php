<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module 3 practitioner profiles tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS sensitive_access_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, entity_type VARCHAR(64) NOT NULL, entity_id INT NOT NULL, field_name VARCHAR(64) NOT NULL, reason VARCHAR(255) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, accessed_at DATETIME NOT NULL, INDEX IDX_SENSITIVE_LOG_USER (user_id), PRIMARY KEY(id), CONSTRAINT FK_SENSITIVE_LOG_USER FOREIGN KEY (user_id) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS credential_files (id INT AUTO_INCREMENT NOT NULL, practitioner_id INT NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes BIGINT NOT NULL, storage_path VARCHAR(1024) NOT NULL, uploaded_at DATETIME NOT NULL, INDEX IDX_CREDENTIAL_FILES_PRACTITIONER (practitioner_id), PRIMARY KEY(id), CONSTRAINT FK_CREDENTIAL_FILES_PRACTITIONER FOREIGN KEY (practitioner_id) REFERENCES practitioners (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS credential_files');
        $this->addSql('DROP TABLE IF EXISTS sensitive_access_logs');
    }
}
