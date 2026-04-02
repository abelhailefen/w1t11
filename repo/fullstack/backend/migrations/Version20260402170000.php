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
        $this->addSql('ALTER TABLE login_attempts CHANGE attempt_at attempted_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE account_lockouts ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE login_attempts CHANGE attempted_at attempt_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE account_lockouts DROP created_at');
    }
}
