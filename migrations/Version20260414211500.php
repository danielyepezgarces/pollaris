<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414211500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the timezone reference used by polls.';
    }

    public function up(Schema $schema): void
    {
        $timezone = date_default_timezone_get();
        $quotedTimezone = $this->connection->quote($timezone);

        $this->addSql("ALTER TABLE poll ADD timezone_mode VARCHAR(20) DEFAULT 'server' NOT NULL");
        $this->addSql("ALTER TABLE poll ADD timezone_name VARCHAR(100) DEFAULT {$quotedTimezone} NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP timezone_mode, DROP timezone_name');
    }
}
