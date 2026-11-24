<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124150000AddProposalMaxVotes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add individual max_votes column to proposal table';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE `proposal` ADD COLUMN IF NOT EXISTS max_votes INT DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE "proposal" ADD max_votes INT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE `proposal` DROP COLUMN IF EXISTS max_votes');
        } else {
            $this->addSql('ALTER TABLE "proposal" DROP COLUMN max_votes');
        }
    }
}
