<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paymongo checkout fields on payment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD paymongo_link_id VARCHAR(255) DEFAULT NULL, ADD paymongo_checkout_url VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP paymongo_link_id, DROP paymongo_checkout_url');
    }
}
