<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// phpcs:disable Generic.Files.LineLength
final class Version20260422202721ChangeMessengerMessagesIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change the Messenger messages index';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'postgresql') {
            $this->addSql('DROP INDEX IF EXISTS idx_75ea56e016ba31db');
            $this->addSql('DROP INDEX IF EXISTS idx_75ea56e0e3bd61ce');
            $this->addSql('DROP INDEX IF EXISTS idx_75ea56e0fb7336f0');
        } else {
            // MySQL / MariaDB syntax
            try {
                $this->connection->executeStatement('DROP INDEX idx_75ea56e016ba31db ON messenger_messages');
            } catch (\Exception) {
            }
            try {
                $this->connection->executeStatement('DROP INDEX idx_75ea56e0e3bd61ce ON messenger_messages');
            } catch (\Exception) {
            }
            try {
                $this->connection->executeStatement('DROP INDEX idx_75ea56e0fb7336f0 ON messenger_messages');
            } catch (\Exception) {
            }
        }

        try {
            if ($platform !== 'postgresql') {
                $this->connection->executeStatement('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
            }
        } catch (\Exception) {
        }

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (
              queue_name, available_at, delivered_at,
              id
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'postgresql') {
            $this->addSql('DROP INDEX IF EXISTS IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
            $this->addSql('CREATE INDEX idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
            $this->addSql('CREATE INDEX idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
            $this->addSql('CREATE INDEX idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
        } else {
            try {
                $this->connection->executeStatement('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
            } catch (\Exception) {
            }
            try {
                $this->connection->executeStatement('CREATE INDEX idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
            } catch (\Exception) {
            }
            try {
                $this->connection->executeStatement('CREATE INDEX idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
            } catch (\Exception) {
            }
            try {
                $this->connection->executeStatement('CREATE INDEX idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
            } catch (\Exception) {
            }
        }
    }
}
