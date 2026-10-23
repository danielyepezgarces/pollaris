<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023163500AddEmailNotificationSettings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_enabled to communities and notify_community_members to poll';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE communities ADD COLUMN IF NOT EXISTS email_enabled TINYINT(1) DEFAULT 1 NOT NULL');
            $this->addSql('ALTER TABLE poll ADD COLUMN IF NOT EXISTS notify_community_members TINYINT(1) DEFAULT 1 NOT NULL');
        } else {
            $this->addSql('ALTER TABLE communities ADD email_enabled TINYINT(1) DEFAULT 1 NOT NULL');
            $this->addSql('ALTER TABLE poll ADD notify_community_members TINYINT(1) DEFAULT 1 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE communities DROP COLUMN IF EXISTS email_enabled');
            $this->addSql('ALTER TABLE poll DROP COLUMN IF EXISTS notify_community_members');
        } else {
            $this->addSql('ALTER TABLE communities DROP COLUMN email_enabled');
            $this->addSql('ALTER TABLE poll DROP COLUMN notify_community_members');
        }
    }
}
