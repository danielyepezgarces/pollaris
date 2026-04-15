<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414224000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Wikimedia OAuth user fields and link polls to their owner.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA457E3C61F9 FOREIGN KEY (owner_id) REFERENCES `users` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_84BCFA457E3C61F9 ON poll (owner_id)');

        $this->addSql('ALTER TABLE `users` ADD wikimedia_id VARCHAR(100) DEFAULT NULL, ADD real_name VARCHAR(255) DEFAULT NULL, ADD email VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E6B7992C94 ON `users` (wikimedia_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA457E3C61F9');
        $this->addSql('DROP INDEX IDX_84BCFA457E3C61F9 ON poll');
        $this->addSql('ALTER TABLE poll DROP owner_id');

        $this->addSql('DROP INDEX UNIQ_1483A5E6B7992C94 ON `users`');
        $this->addSql('ALTER TABLE `users` DROP wikimedia_id, DROP real_name, DROP email');
    }
}
