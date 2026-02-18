<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manifestation_order and manifestation_order_item tables for acquisition workflow.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE manifestation_order (
                id INT AUTO_INCREMENT NOT NULL,
                order_number VARCHAR(32) NOT NULL,
                status VARCHAR(32) NOT NULL,
                ordered_at DATETIME NOT NULL,
                completed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_manifestation_order_number (order_number),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE manifestation_order_item (
                id INT AUTO_INCREMENT NOT NULL,
                order_id INT NOT NULL,
                manifestation_id INT NOT NULL,
                received_at DATETIME DEFAULT NULL,
                UNIQUE INDEX uniq_order_item_manifestation (order_id, manifestation_id),
                INDEX idx_order_item_order (order_id),
                INDEX idx_order_item_manifestation (manifestation_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE manifestation_order_item ADD CONSTRAINT fk_manifestation_order_item_order FOREIGN KEY (order_id) REFERENCES manifestation_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE manifestation_order_item ADD CONSTRAINT fk_manifestation_order_item_manifestation FOREIGN KEY (manifestation_id) REFERENCES manifestation (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manifestation_order_item DROP FOREIGN KEY fk_manifestation_order_item_order');
        $this->addSql('ALTER TABLE manifestation_order_item DROP FOREIGN KEY fk_manifestation_order_item_manifestation');
        $this->addSql('DROP TABLE manifestation_order_item');
        $this->addSql('DROP TABLE manifestation_order');
    }
}
