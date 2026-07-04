<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703154227AddOnDeleteCascadeToCommentsPollId extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add on delete cascade to comment.poll_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment DROP CONSTRAINT fk_9474526c3c947c0f');
        $this->addSql(<<<'SQL'
            ALTER TABLE comment
            ADD CONSTRAINT FK_9474526C3C947C0F
            FOREIGN KEY (poll_id)
            REFERENCES poll (id)
            ON DELETE CASCADE NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment DROP CONSTRAINT FK_9474526C3C947C0F');
        $this->addSql(<<<'SQL'
            ALTER TABLE comment
            ADD CONSTRAINT fk_9474526c3c947c0f
            FOREIGN KEY (poll_id)
            REFERENCES poll (id)
            NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }
}
