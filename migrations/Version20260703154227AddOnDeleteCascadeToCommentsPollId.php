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
        try {
            $this->connection->executeStatement('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C3C947C0F');
        } catch (\Exception) {
            try {
                $this->connection->executeStatement('ALTER TABLE comment DROP FOREIGN KEY fk_9474526c3c947c0f');
            } catch (\Exception) {
                // Si no existe, no hacemos nada
            }
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE comment
            ADD CONSTRAINT FK_9474526C3C947C0F
            FOREIGN KEY (poll_id)
            REFERENCES poll (id)
            ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        try {
            $this->connection->executeStatement('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C3C947C0F');
        } catch (\Exception) {
            try {
                $this->connection->executeStatement('ALTER TABLE comment DROP FOREIGN KEY fk_9474526c3c947c0f');
            } catch (\Exception) {
                // Si no existe, no hacemos nada
            }
        }

        $this->addSql(<<<'SQL'
            ALTER TABLE comment
            ADD CONSTRAINT FK_9474526C3C947C0F
            FOREIGN KEY (poll_id)
            REFERENCES poll (id)
        SQL);
    }
}
