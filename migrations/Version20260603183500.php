<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603183500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter sess_lifetime in sessions table to BIGINT to fix out of range error.';
    }

    public function up(Schema $schema): void
    {
        // Fix for "Numeric value out of range: 1264 Out of range value for column 'sess_lifetime'"
        $this->addSql('ALTER TABLE `sessions` MODIFY `sess_lifetime` BIGINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `sessions` MODIFY `sess_lifetime` INT UNSIGNED NOT NULL');
    }
}
