<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113200000AddPollNotificationDeliverySettings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reminded_milestones, last_leading_proposal_id and last_leader_notified_at to poll table';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE `poll` ADD COLUMN IF NOT EXISTS reminded_milestones JSON DEFAULT NULL');
            $this->addSql('ALTER TABLE `poll` ADD COLUMN IF NOT EXISTS last_leading_proposal_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE `poll` ADD COLUMN IF NOT EXISTS last_leader_notified_at DATETIME DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE "poll" ADD reminded_milestones JSON DEFAULT NULL');
            $this->addSql('ALTER TABLE "poll" ADD last_leading_proposal_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE "poll" ADD last_leader_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE `poll` DROP COLUMN IF EXISTS reminded_milestones');
            $this->addSql('ALTER TABLE `poll` DROP COLUMN IF EXISTS last_leading_proposal_id');
            $this->addSql('ALTER TABLE `poll` DROP COLUMN IF EXISTS last_leader_notified_at');
        } else {
            $this->addSql('ALTER TABLE "poll" DROP COLUMN reminded_milestones');
            $this->addSql('ALTER TABLE "poll" DROP COLUMN last_leading_proposal_id');
            $this->addSql('ALTER TABLE "poll" DROP COLUMN last_leader_notified_at');
        }
    }
}
