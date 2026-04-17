<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Wikimedia voting eligibility settings to polls.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll ADD min_wikimedia_account_age_months INT DEFAULT NULL, ADD min_wikimedia_edits_project VARCHAR(50) DEFAULT NULL, ADD min_wikimedia_edits_count INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP min_wikimedia_account_age_months, DROP min_wikimedia_edits_project, DROP min_wikimedia_edits_count');
    }
}
