<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Persist FCM device tokens on user rows for background push notifications.
 */
final class Version20260528120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.fcm_token for Firebase Cloud Messaging';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD fcm_token VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP fcm_token');
    }
}
