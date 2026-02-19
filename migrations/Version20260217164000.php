<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217164000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exported_at to manifestation_order for order sheet export tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manifestation_order ADD exported_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manifestation_order DROP exported_at');
    }
}
