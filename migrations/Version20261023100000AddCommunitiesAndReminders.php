<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023100000AddCommunitiesAndReminders extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add communities, community members, poll opt-outs and community fields to poll';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS communities (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                join_policy VARCHAR(20) NOT NULL,
                invite_token VARCHAR(64) DEFAULT NULL,
                telegram_chat_id VARCHAR(100) DEFAULT NULL,
                telegram_enabled TINYINT(1) DEFAULT 0 NOT NULL,
                owner_id INT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_COMMUNITY_SLUG (slug),
                INDEX IDX_COMMUNITY_OWNER (owner_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_COMMUNITY_OWNER FOREIGN KEY (owner_id) REFERENCES `users` (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS community_members (
                id INT AUTO_INCREMENT NOT NULL,
                community_id INT NOT NULL,
                user_id INT NOT NULL,
                role VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL,
                receive_emails TINYINT(1) DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_COMMUNITY_USER (community_id, user_id),
                INDEX IDX_COMMUNITY_MEMBER_COMMUNITY (community_id),
                INDEX IDX_COMMUNITY_MEMBER_USER (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_COMM_MEMBER_COMMUNITY FOREIGN KEY (community_id) REFERENCES communities (id) ON DELETE CASCADE,
                CONSTRAINT FK_COMM_MEMBER_USER FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS poll_notification_opt_outs (
                id INT AUTO_INCREMENT NOT NULL,
                poll_id VARCHAR(20) NOT NULL,
                user_id INT DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_POLL_OPT_OUT_USER (poll_id, user_id),
                UNIQUE INDEX UNIQ_POLL_OPT_OUT_EMAIL (poll_id, email),
                INDEX IDX_POLL_OPT_OUT_POLL (poll_id),
                INDEX IDX_POLL_OPT_OUT_USER (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_POLL_OPT_OUT_POLL FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE,
                CONSTRAINT FK_POLL_OPT_OUT_USER FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE poll 
            ADD community_id INT DEFAULT NULL, 
            ADD community_only_vote TINYINT(1) DEFAULT 0 NOT NULL, 
            ADD reminded_at DATETIME DEFAULT NULL,
            ADD CONSTRAINT FK_POLL_COMMUNITY FOREIGN KEY (community_id) REFERENCES communities (id) ON DELETE SET NULL,
            ADD INDEX IDX_POLL_COMMUNITY (community_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_POLL_COMMUNITY');
        $this->addSql('DROP INDEX IDX_POLL_COMMUNITY ON poll');
        $this->addSql('ALTER TABLE poll DROP community_id, DROP community_only_vote, DROP reminded_at');
        $this->addSql('DROP TABLE IF EXISTS poll_notification_opt_outs');
        $this->addSql('DROP TABLE IF EXISTS community_members');
        $this->addSql('DROP TABLE IF EXISTS communities');
    }
}
