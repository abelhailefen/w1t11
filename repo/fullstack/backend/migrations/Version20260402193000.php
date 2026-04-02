<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit_logs table for security events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS audit_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, occurred_at DATETIME NOT NULL, action_type VARCHAR(64) NOT NULL, entity_type VARCHAR(64) DEFAULT NULL, entity_id INT DEFAULT NULL, old_value_json LONGTEXT DEFAULT NULL, new_value_json LONGTEXT DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, retention_expires_at DATETIME DEFAULT NULL, INDEX IDX_AUDIT_LOGS_USER (user_id), INDEX IDX_AUDIT_LOGS_OCCURRED (occurred_at), PRIMARY KEY(id), CONSTRAINT FK_AUDIT_LOGS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
    }
}
