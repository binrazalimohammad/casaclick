<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renter phone on user; activity log IP and platform';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD phone VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE activity_log ADD ip_address VARCHAR(45) DEFAULT NULL, ADD platform VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP phone');
        $this->addSql('ALTER TABLE activity_log DROP ip_address, DROP platform');
    }
}
