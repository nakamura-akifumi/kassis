<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105094725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE manifestation_attachment (id INT AUTO_INCREMENT NOT NULL, manifestation_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(255) NOT NULL, file_size INT NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E759F82ACD8E394E (manifestation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE manifestation_attachment ADD CONSTRAINT FK_E759F82ACD8E394E FOREIGN KEY (manifestation_id) REFERENCES manifestation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manifestation RENAME INDEX unique_identifier TO UNIQ_6F2B3F7F772E836A');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE manifestation_attachment DROP FOREIGN KEY FK_E759F82ACD8E394E');
        $this->addSql('DROP TABLE manifestation_attachment');
        $this->addSql('ALTER TABLE manifestation RENAME INDEX uniq_6f2b3f7f772e836a TO unique_identifier');
    }
}
