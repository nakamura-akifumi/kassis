<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add loan condition table for loan group and member group rules.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE loan_condition (
                id INT AUTO_INCREMENT NOT NULL,
                loan_group_id INT NOT NULL,
                member_group VARCHAR(32) NOT NULL,
                loan_limit INT NOT NULL,
                loan_period INT NOT NULL,
                renew_limit INT NOT NULL,
                reservation_limit INT NOT NULL,
                adjust_due_on_closed_day TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_loan_condition_group (loan_group_id, member_group),
                INDEX IDX_3A43DEB386D61D2 (loan_group_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE loan_condition ADD CONSTRAINT FK_3A43DEB386D61D2 FOREIGN KEY (loan_group_id) REFERENCES loan_group (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan_condition DROP FOREIGN KEY FK_3A43DEB386D61D2');
        $this->addSql('DROP TABLE loan_condition');
    }
}
