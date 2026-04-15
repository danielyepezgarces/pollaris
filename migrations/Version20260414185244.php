<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414185244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE answer (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, value VARCHAR(10) DEFAULT NULL, vote_id VARCHAR(20) NOT NULL, proposal_id INT NOT NULL, INDEX IDX_DADD4A2572DCDAFC (vote_id), INDEX IDX_DADD4A25F4792058 (proposal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, author_name VARCHAR(100) NOT NULL, content LONGTEXT NOT NULL, poll_id VARCHAR(20) NOT NULL, INDEX IDX_9474526C3C947C0F (poll_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE date (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, value DATETIME NOT NULL, poll_id VARCHAR(20) NOT NULL, INDEX IDX_AA9E377A3C947C0F (poll_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE poll (id VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, slug VARCHAR(20) DEFAULT NULL, admin_token VARCHAR(20) NOT NULL, type VARCHAR(20) DEFAULT \'classic\' NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, password VARCHAR(255) DEFAULT \'\' NOT NULL, is_password_for_votes_only TINYINT DEFAULT 0 NOT NULL, author_name VARCHAR(100) NOT NULL, author_email VARCHAR(255) NOT NULL, locale VARCHAR(10) DEFAULT \'fr_FR\' NOT NULL, max_votes INT DEFAULT NULL, notify_on_votes TINYINT DEFAULT 0 NOT NULL, notify_on_comments TINYINT DEFAULT 0 NOT NULL, closed_at DATETIME DEFAULT NULL, are_results_public TINYINT DEFAULT 1 NOT NULL, edit_vote_mode VARCHAR(20) DEFAULT \'own\' NOT NULL, disable_maybe TINYINT DEFAULT 0 NOT NULL, vote_no_by_default TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_84BCFA45989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE proposal (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, label VARCHAR(200) NOT NULL, poll_id VARCHAR(20) NOT NULL, date_id INT DEFAULT NULL, INDEX IDX_BFE594723C947C0F (poll_id), INDEX IDX_BFE59472B897366B (date_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `users` (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, username VARCHAR(100) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vote (id VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, author_name VARCHAR(100) NOT NULL, poll_id VARCHAR(20) NOT NULL, INDEX IDX_5A1085643C947C0F (poll_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_DADD4A2572DCDAFC FOREIGN KEY (vote_id) REFERENCES vote (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_DADD4A25F4792058 FOREIGN KEY (proposal_id) REFERENCES proposal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id)');
        $this->addSql('ALTER TABLE date ADD CONSTRAINT FK_AA9E377A3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE proposal ADD CONSTRAINT FK_BFE594723C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE proposal ADD CONSTRAINT FK_BFE59472B897366B FOREIGN KEY (date_id) REFERENCES date (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A1085643C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A2572DCDAFC');
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A25F4792058');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C3C947C0F');
        $this->addSql('ALTER TABLE date DROP FOREIGN KEY FK_AA9E377A3C947C0F');
        $this->addSql('ALTER TABLE proposal DROP FOREIGN KEY FK_BFE594723C947C0F');
        $this->addSql('ALTER TABLE proposal DROP FOREIGN KEY FK_BFE59472B897366B');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A1085643C947C0F');
        $this->addSql('DROP TABLE answer');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE date');
        $this->addSql('DROP TABLE poll');
        $this->addSql('DROP TABLE proposal');
        $this->addSql('DROP TABLE `users`');
        $this->addSql('DROP TABLE vote');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
