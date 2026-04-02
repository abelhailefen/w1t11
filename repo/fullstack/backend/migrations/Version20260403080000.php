<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module 7 analytics saved queries and features tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS analytics_saved_queries (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, query_definition_json LONGTEXT NOT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_ASQ_CREATED_BY (created_by), PRIMARY KEY(id), CONSTRAINT FK_ASQ_CREATED_BY FOREIGN KEY (created_by) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS analytics_features (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, definition_json LONGTEXT NOT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_AF_CREATED_BY (created_by), PRIMARY KEY(id), CONSTRAINT FK_AF_CREATED_BY FOREIGN KEY (created_by) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS analytics_features');
        $this->addSql('DROP TABLE IF EXISTS analytics_saved_queries');
    }
}
