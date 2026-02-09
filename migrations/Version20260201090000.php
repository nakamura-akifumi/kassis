<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add loan group tables for manifestation type1 grouping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE loan_group (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE loan_group_type1 (
                id INT AUTO_INCREMENT NOT NULL,
                loan_group_id INT NOT NULL,
                type1_identifier VARCHAR(255) NOT NULL,
                UNIQUE INDEX uniq_loan_group_type1_identifier (type1_identifier),
                INDEX IDX_E1B20ACD386D61D2 (loan_group_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE loan_group_type1 ADD CONSTRAINT FK_E1B20ACD386D61D2 FOREIGN KEY (loan_group_id) REFERENCES loan_group (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan_group_type1 DROP FOREIGN KEY FK_E1B20ACD386D61D2');
        $this->addSql('DROP TABLE loan_group_type1');
        $this->addSql('DROP TABLE loan_group');
    }
}
