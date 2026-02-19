<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make manifestation_order.ordered_at nullable for In Progress orders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manifestation_order MODIFY ordered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manifestation_order MODIFY ordered_at DATETIME NOT NULL');
    }
}
