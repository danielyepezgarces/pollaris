<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417052000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add public directory listing flag to polls';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll ADD is_publicly_listed TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP is_publicly_listed');
    }
}
