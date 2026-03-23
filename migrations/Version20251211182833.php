<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211182833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE application (id INT AUTO_INCREMENT NOT NULL, listing_id INT NOT NULL, tenant_id INT NOT NULL, landlord_id INT DEFAULT NULL, status VARCHAR(50) NOT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A45BDDC1D4619D1A (listing_id), INDEX IDX_A45BDDC19033212A (tenant_id), INDEX IDX_A45BDDC1D48E7AED (landlord_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type VARCHAR(50) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT(1) NOT NULL, related_entity VARCHAR(100) DEFAULT NULL, related_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, application_id INT NOT NULL, processed_by_id INT DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, status VARCHAR(50) NOT NULL, payment_method VARCHAR(50) NOT NULL, transaction_id VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6D28840D3E030ACD (application_id), INDEX IDX_6D28840D2FFD4FD3 (processed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_A45BDDC1D4619D1A FOREIGN KEY (listing_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_A45BDDC19033212A FOREIGN KEY (tenant_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_A45BDDC1D48E7AED FOREIGN KEY (landlord_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D3E030ACD FOREIGN KEY (application_id) REFERENCES application (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2FFD4FD3 FOREIGN KEY (processed_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_A45BDDC1D4619D1A');
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_A45BDDC19033212A');
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_A45BDDC1D48E7AED');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D3E030ACD');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D2FFD4FD3');
        $this->addSql('DROP TABLE application');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE payment');
    }
}
