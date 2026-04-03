<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by ownership to practitioners';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'practitioners' AND COLUMN_NAME = 'created_by')");
        $this->addSql("SET @add_col_sql := IF(@has_col = 0, 'ALTER TABLE practitioners ADD created_by INT DEFAULT NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_add_col FROM @add_col_sql');
        $this->addSql('EXECUTE stmt_add_col');
        $this->addSql('DEALLOCATE PREPARE stmt_add_col');

        $this->addSql("SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'practitioners' AND INDEX_NAME = 'IDX_PRACTITIONERS_CREATED_BY')");
        $this->addSql("SET @add_idx_sql := IF(@has_idx = 0, 'CREATE INDEX IDX_PRACTITIONERS_CREATED_BY ON practitioners (created_by)', 'SELECT 1')");
        $this->addSql('PREPARE stmt_add_idx FROM @add_idx_sql');
        $this->addSql('EXECUTE stmt_add_idx');
        $this->addSql('DEALLOCATE PREPARE stmt_add_idx');

        $this->addSql("SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'practitioners' AND CONSTRAINT_NAME = 'FK_PRACTITIONERS_CREATED_BY')");
        $this->addSql("SET @add_fk_sql := IF(@has_fk = 0, 'ALTER TABLE practitioners ADD CONSTRAINT FK_PRACTITIONERS_CREATED_BY FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_add_fk FROM @add_fk_sql');
        $this->addSql('EXECUTE stmt_add_fk');
        $this->addSql('DEALLOCATE PREPARE stmt_add_fk');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'practitioners' AND CONSTRAINT_NAME = 'FK_PRACTITIONERS_CREATED_BY')");
        $this->addSql("SET @drop_fk_sql := IF(@has_fk = 1, 'ALTER TABLE practitioners DROP FOREIGN KEY FK_PRACTITIONERS_CREATED_BY', 'SELECT 1')");
        $this->addSql('PREPARE stmt_drop_fk FROM @drop_fk_sql');
        $this->addSql('EXECUTE stmt_drop_fk');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_fk');

        $this->addSql("SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'practitioners' AND INDEX_NAME = 'IDX_PRACTITIONERS_CREATED_BY')");
        $this->addSql("SET @drop_idx_sql := IF(@has_idx = 1, 'DROP INDEX IDX_PRACTITIONERS_CREATED_BY ON practitioners', 'SELECT 1')");
        $this->addSql('PREPARE stmt_drop_idx FROM @drop_idx_sql');
        $this->addSql('EXECUTE stmt_drop_idx');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_idx');

        $this->addSql("SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'practitioners' AND COLUMN_NAME = 'created_by')");
        $this->addSql("SET @drop_col_sql := IF(@has_col = 1, 'ALTER TABLE practitioners DROP COLUMN created_by', 'SELECT 1')");
        $this->addSql('PREPARE stmt_drop_col FROM @drop_col_sql');
        $this->addSql('EXECUTE stmt_drop_col');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_col');
    }
}
