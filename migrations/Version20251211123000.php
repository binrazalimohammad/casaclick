<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add username, role, and target_data to activity_log for richer audit entries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity_log ADD username VARCHAR(180) DEFAULT NULL, ADD role VARCHAR(60) DEFAULT NULL, ADD target_data LONGTEXT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE activity_log DROP username, DROP role, DROP target_data");
    }
}


