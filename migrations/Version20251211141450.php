<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211141450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit fields (created_by, updated_by, created_at, updated_at) to product table';
    }

    public function up(Schema $schema): void
    {
        // Check if columns exist before adding them
        $connection = $this->connection;
        
        // Check and add created_by_id
        $result = $connection->executeQuery("SELECT COUNT(*) as count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND COLUMN_NAME = 'created_by_id'");
        if ($result->fetchOne() == 0) {
            $this->addSql('ALTER TABLE product ADD created_by_id INT DEFAULT NULL');
        }
        
        // Check and add updated_by_id
        $result = $connection->executeQuery("SELECT COUNT(*) as count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND COLUMN_NAME = 'updated_by_id'");
        if ($result->fetchOne() == 0) {
            $this->addSql('ALTER TABLE product ADD updated_by_id INT DEFAULT NULL');
        }
        
        // Check and add created_at
        $result = $connection->executeQuery("SELECT COUNT(*) as count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND COLUMN_NAME = 'created_at'");
        if ($result->fetchOne() == 0) {
            $this->addSql('ALTER TABLE product ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
        
        // Check and add updated_at
        $result = $connection->executeQuery("SELECT COUNT(*) as count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product' AND COLUMN_NAME = 'updated_at'");
        if ($result->fetchOne() == 0) {
            $this->addSql('ALTER TABLE product ADD updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
        
        // Check and add foreign keys
        $result = $connection->executeQuery("SELECT COUNT(*) as count FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND constraint_name = 'FK_D34A04ADB03A8386'");
        if ($result->fetchOne() == 0) {
            $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
        
        $result = $connection->executeQuery("SELECT COUNT(*) as count FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND constraint_name = 'FK_D34A04AD896DBBDE'");
        if ($result->fetchOne() == 0) {
            $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        }
        
        // Check and add indexes
        $result = $connection->executeQuery("SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'product' AND index_name = 'IDX_D34A04ADB03A8386'");
        if ($result->fetchOne() == 0) {
            $this->addSql('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');
        }
        
        $result = $connection->executeQuery("SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'product' AND index_name = 'IDX_D34A04AD896DBBDE'");
        if ($result->fetchOne() == 0) {
            $this->addSql('CREATE INDEX IDX_D34A04AD896DBBDE ON product (updated_by_id)');
        }
    }

    public function down(Schema $schema): void
    {
        // Remove audit fields from product table
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD896DBBDE');
        $this->addSql('DROP INDEX IDX_D34A04ADB03A8386 ON product');
        $this->addSql('DROP INDEX IDX_D34A04AD896DBBDE ON product');
        $this->addSql('ALTER TABLE product DROP created_by_id, DROP updated_by_id, DROP created_at, DROP updated_at');
    }
}
