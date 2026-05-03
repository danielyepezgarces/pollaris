<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260503110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add poll cohosts and optional proposal time fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll ADD duration INT DEFAULT NULL');
        $this->addSql('ALTER TABLE proposal ADD start_time VARCHAR(5) DEFAULT NULL, ADD end_time VARCHAR(5) DEFAULT NULL');
        $this->addSql('CREATE TABLE poll_cohost (poll_id VARCHAR(20) NOT NULL, user_id INT NOT NULL, cohost_right VARCHAR(10) NOT NULL, INDEX IDX_9A2B122E3C947C0F (poll_id), INDEX IDX_9A2B122EA76ED395 (user_id), PRIMARY KEY(poll_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE poll_cohost ADD CONSTRAINT FK_9A2B122E3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll_cohost ADD CONSTRAINT FK_9A2B122EA76ED395 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll_cohost DROP FOREIGN KEY FK_9A2B122E3C947C0F');
        $this->addSql('ALTER TABLE poll_cohost DROP FOREIGN KEY FK_9A2B122EA76ED395');
        $this->addSql('DROP TABLE poll_cohost');
        $this->addSql('ALTER TABLE proposal DROP start_time, DROP end_time');
        $this->addSql('ALTER TABLE poll DROP duration');
    }
}
