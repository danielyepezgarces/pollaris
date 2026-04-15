<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user owner relationship to votes for database synchronization.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vote ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_F585C8627E3C61F9 FOREIGN KEY (owner_id) REFERENCES `users` (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_F585C8627E3C61F9 ON vote (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_F585C8627E3C61F9');
        $this->addSql('DROP INDEX IDX_F585C8627E3C61F9 ON vote');
        $this->addSql('ALTER TABLE vote DROP owner_id');
    }
}
