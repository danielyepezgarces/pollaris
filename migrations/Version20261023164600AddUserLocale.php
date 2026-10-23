<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023164600AddUserLocale extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add locale column to users table';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE `users` ADD COLUMN IF NOT EXISTS locale VARCHAR(20) DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE `users` ADD locale VARCHAR(20) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $isMySqlOrMariaDb = $platform instanceof AbstractMySQLPlatform;

        if ($isMySqlOrMariaDb) {
            $this->addSql('ALTER TABLE `users` DROP COLUMN IF EXISTS locale');
        } else {
            $this->addSql('ALTER TABLE `users` DROP COLUMN locale');
        }
    }
}
