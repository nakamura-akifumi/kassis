<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250406135720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE manifestation (id INT AUTO_INCREMENT NOT NULL,
                              title LONGTEXT NOT NULL,
                              title_transcription LONGTEXT DEFAULT NULL,
                              identifier VARCHAR(64) NOT NULL,
                              external_identifier1 VARCHAR(64) DEFAULT NULL,
                              external_identifier2 VARCHAR(64) DEFAULT NULL,
                              external_identifier3 VARCHAR(64) DEFAULT NULL, 
                              description LONGTEXT DEFAULT NULL, 
                              buyer VARCHAR(255), 
                              buyer_identifier LONGTEXT, 
                              purchase_date DATE DEFAULT NULL,
                              record_source LONGTEXT DEFAULT NULL,
                              type1 VARCHAR(255) NULL,
                              type2 VARCHAR(255) DEFAULT NULL,
                              type3 VARCHAR(255) DEFAULT NULL,
                              type4 VARCHAR(255) DEFAULT NULL,
                              class1 VARCHAR(32) DEFAULT NULL,
                              class2 VARCHAR(32) DEFAULT NULL,
                              location1 VARCHAR(255) DEFAULT NULL,
                              location2 VARCHAR(255) DEFAULT NULL,
                              location3 VARCHAR(255) DEFAULT NULL,
                              contributor1 VARCHAR(255) DEFAULT NULL,
                              contributor2 VARCHAR(255) DEFAULT NULL,
                              loan_restriction VARCHAR(16) DEFAULT NULL,
                              release_date_string VARCHAR(255) DEFAULT NULL,
                              release_date_start DATE DEFAULT NULL, 
                              release_date_end DATE DEFAULT NULL,
                              price DECIMAL(11,2) DEFAULT NULL,
                              price_currency VARCHAR(3) DEFAULT NULL,
                              status1 VARCHAR(16) NOT NULL,
                              status2 VARCHAR(16) DEFAULT NULL,
                              extinfo LONGTEXT DEFAULT NULL,
                              created_at DATETIME NOT NULL,
                              updated_at DATETIME NOT NULL,
                              PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
            ALTER TABLE manifestation ADD CONSTRAINT unique_identifier UNIQUE (identifier);
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE manifestation
        SQL);
    }
}
