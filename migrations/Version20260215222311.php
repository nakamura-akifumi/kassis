<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215222311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE calendar_event (id INT AUTO_INCREMENT NOT NULL, uid VARCHAR(255) NOT NULL, summary LONGTEXT NOT NULL, description LONGTEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, dt_start DATETIME NOT NULL, dt_end DATETIME DEFAULT NULL, all_day TINYINT(1) NOT NULL, timezone VARCHAR(64) DEFAULT NULL, recurrence_id DATETIME DEFAULT NULL, rrule LONGTEXT DEFAULT NULL, rdate LONGTEXT DEFAULT NULL, exdate LONGTEXT DEFAULT NULL, status VARCHAR(32) DEFAULT NULL, transparency VARCHAR(32) DEFAULT NULL, organizer VARCHAR(255) DEFAULT NULL, sequence INT DEFAULT NULL, is_closed TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_calendar_event_uid_recurrence (uid, recurrence_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE checkout (id INT AUTO_INCREMENT NOT NULL, manifestation_id INT NOT NULL, member_id INT NOT NULL, checked_out_at DATETIME NOT NULL, due_date DATETIME DEFAULT NULL, checked_in_at DATETIME DEFAULT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_AF382D4ECD8E394E (manifestation_id), INDEX IDX_AF382D4E7597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE code (type VARCHAR(32) NOT NULL, identifier VARCHAR(255) NOT NULL, value VARCHAR(32) NOT NULL, displayname VARCHAR(255) DEFAULT NULL, display_order INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(type, identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inventory_session (id INT AUTO_INCREMENT NOT NULL, location VARCHAR(255) NOT NULL, identifier VARCHAR(255) NOT NULL, scanned_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE loan_condition (id INT AUTO_INCREMENT NOT NULL, loan_group_id INT DEFAULT NULL, member_group VARCHAR(32) NOT NULL, loan_limit INT NOT NULL, loan_period INT NOT NULL, renew_limit INT NOT NULL, reservation_limit INT NOT NULL, adjust_due_on_closed_day TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_FC85B4D9A91223EC (loan_group_id), UNIQUE INDEX uniq_loan_condition_group (loan_group_id, member_group), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE loan_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE loan_group_type1 (id INT AUTO_INCREMENT NOT NULL, loan_group_id INT NOT NULL, type1_identifier VARCHAR(255) NOT NULL, INDEX IDX_350FDDAFA91223EC (loan_group_id), UNIQUE INDEX uniq_loan_group_type1_identifier (type1_identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE manifestation (id INT AUTO_INCREMENT NOT NULL, title LONGTEXT NOT NULL, title_transcription LONGTEXT DEFAULT NULL, identifier VARCHAR(255) NOT NULL, external_identifier1 VARCHAR(255) DEFAULT NULL, external_identifier2 VARCHAR(255) DEFAULT NULL, external_identifier3 VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, buyer VARCHAR(255) DEFAULT NULL, buyer_identifier LONGTEXT DEFAULT NULL, purchase_date DATE DEFAULT NULL, record_source LONGTEXT DEFAULT NULL, type1 VARCHAR(255) DEFAULT NULL, type2 VARCHAR(255) DEFAULT NULL, type3 VARCHAR(255) DEFAULT NULL, type4 VARCHAR(255) DEFAULT NULL, location1 VARCHAR(255) DEFAULT NULL, location2 VARCHAR(255) DEFAULT NULL, location3 VARCHAR(255) DEFAULT NULL, contributor1 VARCHAR(255) DEFAULT NULL, contributor2 VARCHAR(255) DEFAULT NULL, loan_restriction VARCHAR(32) DEFAULT NULL, release_date_string VARCHAR(255) DEFAULT NULL, release_date_start DATE DEFAULT NULL, release_date_end DATE DEFAULT NULL, price NUMERIC(11, 2) DEFAULT NULL, price_currency VARCHAR(3) DEFAULT NULL, class1 VARCHAR(32) DEFAULT NULL, class2 VARCHAR(32) DEFAULT NULL, extinfo LONGTEXT DEFAULT NULL, status1 VARCHAR(255) NOT NULL, status2 VARCHAR(16) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6F2B3F7F772E836A (identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE manifestation_attachment (id INT AUTO_INCREMENT NOT NULL, manifestation_id INT NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(255) NOT NULL, file_size INT NOT NULL, mime_type VARCHAR(100) DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E759F82ACD8E394E (manifestation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `member` (id INT AUTO_INCREMENT NOT NULL, identifier VARCHAR(255) NOT NULL, full_name VARCHAR(255) NOT NULL, full_name_transcription VARCHAR(255) DEFAULT NULL, group1 VARCHAR(32) NOT NULL, group2 VARCHAR(32) DEFAULT NULL, communication_address1 VARCHAR(256) DEFAULT NULL, communication_address2 VARCHAR(256) DEFAULT NULL, role VARCHAR(32) DEFAULT NULL, status VARCHAR(32) DEFAULT NULL, note LONGTEXT DEFAULT NULL, expiry_date DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_70E4FA78772E836A (identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, manifestation_id INT NOT NULL, member_id INT NOT NULL, reserved_at BIGINT NOT NULL, expiry_date BIGINT DEFAULT NULL, status VARCHAR(32) NOT NULL, INDEX IDX_42C84955CD8E394E (manifestation_id), INDEX IDX_42C849557597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE checkout ADD CONSTRAINT FK_AF382D4ECD8E394E FOREIGN KEY (manifestation_id) REFERENCES manifestation (id)');
        $this->addSql('ALTER TABLE checkout ADD CONSTRAINT FK_AF382D4E7597D3FE FOREIGN KEY (member_id) REFERENCES `member` (id)');
        $this->addSql('ALTER TABLE loan_condition ADD CONSTRAINT FK_FC85B4D9A91223EC FOREIGN KEY (loan_group_id) REFERENCES loan_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE loan_group_type1 ADD CONSTRAINT FK_350FDDAFA91223EC FOREIGN KEY (loan_group_id) REFERENCES loan_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manifestation_attachment ADD CONSTRAINT FK_E759F82ACD8E394E FOREIGN KEY (manifestation_id) REFERENCES manifestation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955CD8E394E FOREIGN KEY (manifestation_id) REFERENCES manifestation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849557597D3FE FOREIGN KEY (member_id) REFERENCES `member` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checkout DROP FOREIGN KEY FK_AF382D4ECD8E394E');
        $this->addSql('ALTER TABLE checkout DROP FOREIGN KEY FK_AF382D4E7597D3FE');
        $this->addSql('ALTER TABLE loan_condition DROP FOREIGN KEY FK_FC85B4D9A91223EC');
        $this->addSql('ALTER TABLE loan_group_type1 DROP FOREIGN KEY FK_350FDDAFA91223EC');
        $this->addSql('ALTER TABLE manifestation_attachment DROP FOREIGN KEY FK_E759F82ACD8E394E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955CD8E394E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849557597D3FE');
        $this->addSql('DROP TABLE calendar_event');
        $this->addSql('DROP TABLE checkout');
        $this->addSql('DROP TABLE code');
        $this->addSql('DROP TABLE inventory_session');
        $this->addSql('DROP TABLE loan_condition');
        $this->addSql('DROP TABLE loan_group');
        $this->addSql('DROP TABLE loan_group_type1');
        $this->addSql('DROP TABLE manifestation');
        $this->addSql('DROP TABLE manifestation_attachment');
        $this->addSql('DROP TABLE `member`');
        $this->addSql('DROP TABLE reservation');
    }
}
