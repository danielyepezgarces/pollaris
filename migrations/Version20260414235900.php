<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add localized_descriptions JSON column to poll.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE poll ADD localized_descriptions LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP localized_descriptions');
    }
}
