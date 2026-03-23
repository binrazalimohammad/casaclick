<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification and Google OAuth fields to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD email_verified TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE user ADD verification_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD google_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP email_verified');
        $this->addSql('ALTER TABLE user DROP verification_token');
        $this->addSql('ALTER TABLE user DROP google_id');
    }
}
