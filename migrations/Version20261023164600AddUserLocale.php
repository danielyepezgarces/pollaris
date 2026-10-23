<?php

declare(strict_types=1);

namespace DoctrineMigrations;

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
        $this->addSql('ALTER TABLE `users` ADD locale VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `users` DROP COLUMN locale');
    }
}
