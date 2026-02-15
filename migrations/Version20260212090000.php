<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add calendar tables for holidays and organization events.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE calendar_event (
                id INT AUTO_INCREMENT NOT NULL,
                uid VARCHAR(255) NOT NULL,
                summary TEXT NOT NULL,
                description LONGTEXT DEFAULT NULL,
                location VARCHAR(255) DEFAULT NULL,
                dt_start DATETIME NOT NULL,
                dt_end DATETIME DEFAULT NULL,
                all_day TINYINT(1) NOT NULL,
                timezone VARCHAR(64) DEFAULT NULL,
                recurrence_id DATETIME DEFAULT NULL,
                rrule TEXT DEFAULT NULL,
                rdate TEXT DEFAULT NULL,
                exdate TEXT DEFAULT NULL,
                status VARCHAR(32) DEFAULT NULL,
                transparency VARCHAR(32) DEFAULT NULL,
                organizer VARCHAR(255) DEFAULT NULL,
                sequence INT DEFAULT NULL,
                is_closed TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_calendar_event_uid_recurrence (uid, recurrence_id),
                INDEX idx_calendar_event_start (dt_start),
                INDEX idx_calendar_event_end (dt_end),
                INDEX idx_calendar_event_uid (uid),
                INDEX idx_calendar_event_closed (is_closed),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE calendar_event');
    }
}
