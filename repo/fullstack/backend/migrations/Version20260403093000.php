<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add org_unit_id to firms for KPI scoping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms' AND COLUMN_NAME = 'org_unit_id')");
        $this->addSql("SET @add_col_sql := IF(@has_col = 0, 'ALTER TABLE firms ADD org_unit_id INT DEFAULT NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_add_col FROM @add_col_sql');
        $this->addSql('EXECUTE stmt_add_col');
        $this->addSql('DEALLOCATE PREPARE stmt_add_col');

        $this->addSql("SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms' AND INDEX_NAME = 'IDX_FIRMS_ORG_UNIT')");
        $this->addSql("SET @add_idx_sql := IF(@has_idx = 0, 'CREATE INDEX IDX_FIRMS_ORG_UNIT ON firms (org_unit_id)', 'SELECT 1')");
        $this->addSql('PREPARE stmt_add_idx FROM @add_idx_sql');
        $this->addSql('EXECUTE stmt_add_idx');
        $this->addSql('DEALLOCATE PREPARE stmt_add_idx');

        $this->addSql("SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'firms' AND CONSTRAINT_NAME = 'FK_FIRMS_ORG_UNIT')");
        $this->addSql("SET @add_fk_sql := IF(@has_fk = 0, 'ALTER TABLE firms ADD CONSTRAINT FK_FIRMS_ORG_UNIT FOREIGN KEY (org_unit_id) REFERENCES org_units (id) ON DELETE SET NULL', 'SELECT 1')");
        $this->addSql('PREPARE stmt_add_fk FROM @add_fk_sql');
        $this->addSql('EXECUTE stmt_add_fk');
        $this->addSql('DEALLOCATE PREPARE stmt_add_fk');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'firms' AND CONSTRAINT_NAME = 'FK_FIRMS_ORG_UNIT')");
        $this->addSql("SET @drop_fk_sql := IF(@has_fk = 1, 'ALTER TABLE firms DROP FOREIGN KEY FK_FIRMS_ORG_UNIT', 'SELECT 1')");
        $this->addSql('PREPARE stmt_drop_fk FROM @drop_fk_sql');
        $this->addSql('EXECUTE stmt_drop_fk');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_fk');

        $this->addSql("SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms' AND INDEX_NAME = 'IDX_FIRMS_ORG_UNIT')");
        $this->addSql("SET @drop_idx_sql := IF(@has_idx = 1, 'DROP INDEX IDX_FIRMS_ORG_UNIT ON firms', 'SELECT 1')");
        $this->addSql('PREPARE stmt_drop_idx FROM @drop_idx_sql');
        $this->addSql('EXECUTE stmt_drop_idx');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_idx');

        $this->addSql("SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms' AND COLUMN_NAME = 'org_unit_id')");
        $this->addSql("SET @drop_col_sql := IF(@has_col = 1, 'ALTER TABLE firms DROP COLUMN org_unit_id', 'SELECT 1')");
        $this->addSql('PREPARE stmt_drop_col FROM @drop_col_sql');
        $this->addSql('EXECUTE stmt_drop_col');
        $this->addSql('DEALLOCATE PREPARE stmt_drop_col');
    }
}
