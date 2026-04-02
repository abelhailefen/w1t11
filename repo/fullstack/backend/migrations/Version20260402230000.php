<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module 4 credential workflow tables and credential_files relation update';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS credential_submissions (id INT AUTO_INCREMENT NOT NULL, practitioner_id INT NOT NULL, current_state VARCHAR(40) NOT NULL, created_by_id INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_CREDENTIAL_SUBMISSIONS_PRACTITIONER (practitioner_id), INDEX IDX_CREDENTIAL_SUBMISSIONS_CREATED_BY (created_by_id), PRIMARY KEY(id), CONSTRAINT FK_CREDENTIAL_SUBMISSIONS_PRACTITIONER FOREIGN KEY (practitioner_id) REFERENCES practitioners (id) ON DELETE CASCADE, CONSTRAINT FK_CREDENTIAL_SUBMISSIONS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS credential_versions (id INT AUTO_INCREMENT NOT NULL, submission_id INT NOT NULL, version_no INT NOT NULL, payload_json LONGTEXT NOT NULL, state VARCHAR(40) NOT NULL, rejection_comment LONGTEXT DEFAULT NULL, created_by_id INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_CREDENTIAL_VERSION_NO (submission_id, version_no), INDEX IDX_CREDENTIAL_VERSIONS_CREATED_BY (created_by_id), PRIMARY KEY(id), CONSTRAINT FK_CREDENTIAL_VERSIONS_SUBMISSION FOREIGN KEY (submission_id) REFERENCES credential_submissions (id) ON DELETE CASCADE, CONSTRAINT FK_CREDENTIAL_VERSIONS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("SET @has_credential_version := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credential_files' AND COLUMN_NAME = 'credential_version_id');");
        $this->addSql("SET @add_credential_version_sql := IF(@has_credential_version = 0, 'ALTER TABLE credential_files ADD credential_version_id INT DEFAULT NULL', 'SELECT 1');");
        $this->addSql('PREPARE stmt FROM @add_credential_version_sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        $this->addSql("SET @has_idx_version := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credential_files' AND INDEX_NAME = 'IDX_CREDENTIAL_FILES_VERSION');");
        $this->addSql("SET @add_idx_version_sql := IF(@has_idx_version = 0, 'CREATE INDEX IDX_CREDENTIAL_FILES_VERSION ON credential_files (credential_version_id)', 'SELECT 1');");
        $this->addSql('PREPARE stmt FROM @add_idx_version_sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        $this->addSql("INSERT INTO credential_submissions (practitioner_id, current_state, created_by_id, created_at, updated_at) SELECT DISTINCT cf.practitioner_id, 'DRAFT', 1, NOW(), NOW() FROM credential_files cf LEFT JOIN credential_submissions cs ON cs.practitioner_id = cf.practitioner_id WHERE cf.practitioner_id IS NOT NULL AND cs.id IS NULL");
        $this->addSql("INSERT INTO credential_versions (submission_id, version_no, payload_json, state, rejection_comment, created_by_id, created_at) SELECT cs.id, 1, '{}', cs.current_state, NULL, cs.created_by_id, NOW() FROM credential_submissions cs LEFT JOIN credential_versions cv ON cv.submission_id = cs.id AND cv.version_no = 1 WHERE cv.id IS NULL");
        $this->addSql('UPDATE credential_files cf JOIN credential_submissions cs ON cs.practitioner_id = cf.practitioner_id JOIN credential_versions cv ON cv.submission_id = cs.id AND cv.version_no = 1 SET cf.credential_version_id = cv.id WHERE cf.credential_version_id IS NULL');

        $this->addSql('ALTER TABLE credential_files MODIFY credential_version_id INT NOT NULL');
        $this->addSql("SET @has_fk_version := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'credential_files' AND CONSTRAINT_NAME = 'FK_CREDENTIAL_FILES_VERSION');");
        $this->addSql("SET @add_fk_version_sql := IF(@has_fk_version = 0, 'ALTER TABLE credential_files ADD CONSTRAINT FK_CREDENTIAL_FILES_VERSION FOREIGN KEY (credential_version_id) REFERENCES credential_versions (id) ON DELETE CASCADE', 'SELECT 1');");
        $this->addSql('PREPARE stmt FROM @add_fk_version_sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        $this->addSql("SET @has_fk_practitioner := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'credential_files' AND CONSTRAINT_NAME = 'FK_CREDENTIAL_FILES_PRACTITIONER');");
        $this->addSql("SET @drop_fk_practitioner_sql := IF(@has_fk_practitioner = 1, 'ALTER TABLE credential_files DROP FOREIGN KEY FK_CREDENTIAL_FILES_PRACTITIONER', 'SELECT 1');");
        $this->addSql('PREPARE stmt FROM @drop_fk_practitioner_sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        $this->addSql("SET @has_idx_practitioner := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credential_files' AND INDEX_NAME = 'IDX_CREDENTIAL_FILES_PRACTITIONER');");
        $this->addSql("SET @drop_idx_practitioner_sql := IF(@has_idx_practitioner = 1, 'DROP INDEX IDX_CREDENTIAL_FILES_PRACTITIONER ON credential_files', 'SELECT 1');");
        $this->addSql('PREPARE stmt FROM @drop_idx_practitioner_sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');

        $this->addSql("SET @has_practitioner_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credential_files' AND COLUMN_NAME = 'practitioner_id');");
        $this->addSql("SET @drop_practitioner_col_sql := IF(@has_practitioner_col = 1, 'ALTER TABLE credential_files DROP COLUMN practitioner_id', 'SELECT 1');");
        $this->addSql('PREPARE stmt FROM @drop_practitioner_col_sql');
        $this->addSql('EXECUTE stmt');
        $this->addSql('DEALLOCATE PREPARE stmt');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credential_files ADD practitioner_id INT DEFAULT NULL');
        $this->addSql('UPDATE credential_files cf JOIN credential_versions cv ON cf.credential_version_id = cv.id JOIN credential_submissions cs ON cv.submission_id = cs.id SET cf.practitioner_id = cs.practitioner_id');
        $this->addSql('ALTER TABLE credential_files MODIFY practitioner_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_CREDENTIAL_FILES_PRACTITIONER ON credential_files (practitioner_id)');
        $this->addSql('ALTER TABLE credential_files ADD CONSTRAINT FK_CREDENTIAL_FILES_PRACTITIONER FOREIGN KEY (practitioner_id) REFERENCES practitioners (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE credential_files DROP FOREIGN KEY FK_CREDENTIAL_FILES_VERSION');
        $this->addSql('DROP INDEX IDX_CREDENTIAL_FILES_VERSION ON credential_files');
        $this->addSql('ALTER TABLE credential_files DROP COLUMN credential_version_id');

        $this->addSql('DROP TABLE IF EXISTS credential_versions');
        $this->addSql('DROP TABLE IF EXISTS credential_submissions');
    }
}
