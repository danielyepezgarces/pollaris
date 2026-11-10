<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110150500AddCommunityLocalizedDescriptions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add localized_descriptions column to communities table';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE `communities` ADD COLUMN IF NOT EXISTS localized_descriptions JSON DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE `communities` ADD localized_descriptions CLOB DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE `communities` DROP COLUMN IF EXISTS localized_descriptions');
        } else {
            $this->addSql('ALTER TABLE `communities` DROP COLUMN localized_descriptions');
        }
    }
}
