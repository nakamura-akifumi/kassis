<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order detail fields to manifestation_order.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manifestation_order ADD vendor VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE manifestation_order ADD order_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE manifestation_order ADD delivery_due_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE manifestation_order ADD estimate_number VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE manifestation_order ADD vendor_contact VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE manifestation_order ADD order_contact VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE manifestation_order ADD external_reference VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE manifestation_order ADD memo LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manifestation_order DROP vendor');
        $this->addSql('ALTER TABLE manifestation_order DROP order_amount');
        $this->addSql('ALTER TABLE manifestation_order DROP delivery_due_date');
        $this->addSql('ALTER TABLE manifestation_order DROP estimate_number');
        $this->addSql('ALTER TABLE manifestation_order DROP vendor_contact');
        $this->addSql('ALTER TABLE manifestation_order DROP order_contact');
        $this->addSql('ALTER TABLE manifestation_order DROP external_reference');
        $this->addSql('ALTER TABLE manifestation_order DROP memo');
    }
}
