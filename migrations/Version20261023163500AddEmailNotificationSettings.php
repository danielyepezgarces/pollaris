<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023163500AddEmailNotificationSettings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_enabled to communities and notify_community_members to polls';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communities ADD email_enabled TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE polls ADD notify_community_members TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communities DROP COLUMN email_enabled');
        $this->addSql('ALTER TABLE polls DROP COLUMN notify_community_members');
    }
}
