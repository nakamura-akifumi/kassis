<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260115090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add member, reservation, checkout tables and normalize manifestation status.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE member (
                id INT AUTO_INCREMENT NOT NULL,
                identifier VARCHAR(32) NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                full_name_transcription VARCHAR(255) DEFAULT NULL,
                `group1` VARCHAR(32) NOT NULL DEFAULT 'standard',
                `group2` VARCHAR(32) DEFAULT NULL,
                communication_address1 VARCHAR(256) DEFAULT NULL,
                communication_address2 VARCHAR(256) DEFAULT NULL,
                role VARCHAR(32) DEFAULT NULL,
                expiry_date DATE DEFAULT NULL,
                status VARCHAR(32) NOT NULL,
                note LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_70E4FA78C05FB297 (identifier),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE reservation (
                id INT AUTO_INCREMENT NOT NULL,
                manifestation_id INT NOT NULL,
                member_id INT NOT NULL,
                reserved_at BIGINT NOT NULL,
                expiry_date BIGINT DEFAULT NULL,
                status VARCHAR(32) NOT NULL,
                INDEX IDX_42C84955E076A465 (manifestation_id),
                INDEX IDX_42C849557597D3FE (member_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE checkout (
                id INT AUTO_INCREMENT NOT NULL,
                manifestation_id INT NOT NULL,
                member_id INT NOT NULL,
                checked_out_at DATETIME NOT NULL,
                due_date DATETIME DEFAULT NULL,
                checked_in_at DATETIME DEFAULT NULL,
                status VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_5F7BF1D1E076A465 (manifestation_id),
                INDEX IDX_5F7BF1D17597D3FE (member_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955E076A465 FOREIGN KEY (manifestation_id) REFERENCES manifestation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849557597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE checkout ADD CONSTRAINT FK_5F7BF1D1E076A465 FOREIGN KEY (manifestation_id) REFERENCES manifestation (id)');
        $this->addSql('ALTER TABLE checkout ADD CONSTRAINT FK_5F7BF1D17597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955E076A465');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849557597D3FE');
        $this->addSql('ALTER TABLE checkout DROP FOREIGN KEY FK_5F7BF1D1E076A465');
        $this->addSql('ALTER TABLE checkout DROP FOREIGN KEY FK_5F7BF1D17597D3FE');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE checkout');
        $this->addSql('DROP TABLE member');
    }
}
