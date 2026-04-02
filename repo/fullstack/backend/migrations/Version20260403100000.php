<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module 8 alerts table and audit retention index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS alerts (id INT AUTO_INCREMENT NOT NULL, acknowledged_by INT DEFAULT NULL, alert_type VARCHAR(64) NOT NULL, severity VARCHAR(16) NOT NULL, message VARCHAR(500) NOT NULL, context_json LONGTEXT DEFAULT NULL, triggered_at DATETIME NOT NULL, acknowledged_at DATETIME DEFAULT NULL, INDEX IDX_ALERT_ACK_BY (acknowledged_by), INDEX IDX_ALERT_SEVERITY (severity), INDEX IDX_ALERT_ACK_AT (acknowledged_at), PRIMARY KEY(id), CONSTRAINT FK_ALERT_ACK_BY FOREIGN KEY (acknowledged_by) REFERENCES users (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_AUDIT_RETENTION_EXPIRES ON audit_logs (retention_expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS alerts');
        $this->addSql('DROP INDEX IDX_AUDIT_RETENTION_EXPIRES ON audit_logs');
    }
}
