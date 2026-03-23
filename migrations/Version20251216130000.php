<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251216130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to product for admin approval workflow';
    }

    public function up(Schema $schema): void
    {
        // New listings will be explicitly set to "pending" in the controller.
        // Existing listings should be considered approved.
        $this->addSql("ALTER TABLE product ADD status VARCHAR(20) NOT NULL DEFAULT 'approved'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP status');
    }
}


