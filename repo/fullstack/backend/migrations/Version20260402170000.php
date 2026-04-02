<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Auth module schema adjustments for lockout and login attempts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("SET @has_attempted := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_attempts' AND COLUMN_NAME = 'attempted_at');");
        $this->addSql("SET @rename_attempt_sql := IF(@has_attempted = 0, 'ALTER TABLE login_attempts CHANGE attempt_at attempted_at DATETIME NOT NULL', 'SELECT 1');");
        $this->addSql('PREPARE stmt FROM @rename_attempt_sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        $this->addSql("SET @has_created_at := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'account_lockouts' AND COLUMN_NAME = 'created_at');");
        $this->addSql("SET @add_created_at_sql := IF(@has_created_at = 0, 'ALTER TABLE account_lockouts ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');");
        $this->addSql('PREPARE stmt FROM @add_created_at_sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE login_attempts CHANGE attempted_at attempt_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE account_lockouts DROP created_at');
    }
}
