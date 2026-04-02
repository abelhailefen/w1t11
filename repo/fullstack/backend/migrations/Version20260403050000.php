<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module 6 question bank tables and question versioning fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE questions ADD current_version_id INT DEFAULT NULL, ADD duplicate_acknowledged TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE IF NOT EXISTS question_versions (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, version_no INT NOT NULL, content_html LONGTEXT NOT NULL, plain_text_index LONGTEXT NOT NULL, difficulty INT NOT NULL, metadata_json LONGTEXT DEFAULT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_QUESTION_VERSION_NO (question_id, version_no), INDEX IDX_QV_QUESTION (question_id), INDEX IDX_QV_CREATED_BY (created_by), PRIMARY KEY(id), CONSTRAINT FK_QV_QUESTION FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE, CONSTRAINT FK_QV_CREATED_BY FOREIGN KEY (created_by) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE questions ADD CONSTRAINT FK_QUESTIONS_CURRENT_VERSION FOREIGN KEY (current_version_id) REFERENCES question_versions (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_QUESTIONS_CURRENT_VERSION ON questions (current_version_id)');

        $this->addSql('CREATE TABLE IF NOT EXISTS question_tag_map (question_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_QTM_QUESTION (question_id), INDEX IDX_QTM_TAG (tag_id), PRIMARY KEY(question_id, tag_id), CONSTRAINT FK_QTM_QUESTION FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE, CONSTRAINT FK_QTM_TAG FOREIGN KEY (tag_id) REFERENCES question_tags (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS question_import_jobs (id INT AUTO_INCREMENT NOT NULL, initiated_by INT NOT NULL, file_name VARCHAR(255) NOT NULL, format VARCHAR(16) NOT NULL, status VARCHAR(20) NOT NULL, result_json LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, INDEX IDX_QIJ_USER (initiated_by), PRIMARY KEY(id), CONSTRAINT FK_QIJ_USER FOREIGN KEY (initiated_by) REFERENCES users (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS question_similarity_flags (id INT AUTO_INCREMENT NOT NULL, question_version_id INT NOT NULL, matched_question_id INT NOT NULL, similarity_score DOUBLE PRECISION NOT NULL, threshold DOUBLE PRECISION NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_QSF_VERSION (question_version_id), INDEX IDX_QSF_MATCHED (matched_question_id), PRIMARY KEY(id), CONSTRAINT FK_QSF_VERSION FOREIGN KEY (question_version_id) REFERENCES question_versions (id) ON DELETE CASCADE, CONSTRAINT FK_QSF_MATCHED FOREIGN KEY (matched_question_id) REFERENCES questions (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS question_similarity_flags');
        $this->addSql('DROP TABLE IF EXISTS question_import_jobs');
        $this->addSql('DROP TABLE IF EXISTS question_tag_map');
        $this->addSql('ALTER TABLE questions DROP FOREIGN KEY FK_QUESTIONS_CURRENT_VERSION');
        $this->addSql('DROP INDEX IDX_QUESTIONS_CURRENT_VERSION ON questions');
        $this->addSql('DROP TABLE IF EXISTS question_versions');
        $this->addSql('ALTER TABLE questions DROP COLUMN current_version_id, DROP COLUMN duplicate_acknowledged');
    }
}
