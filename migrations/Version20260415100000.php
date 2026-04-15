<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on vote(owner_id, poll_id) to prevent duplicate votes per user.';
    }

    public function up(Schema $schema): void
    {
        // NULL values are excluded from the unique check in MariaDB/MySQL,
        // so anonymous votes (owner_id IS NULL) are unaffected.
        $this->addSql('CREATE UNIQUE INDEX UNIQ_VOTE_OWNER_POLL ON vote (owner_id, poll_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_VOTE_OWNER_POLL ON vote');
    }
}
