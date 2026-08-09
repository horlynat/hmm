<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260809162428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ticketing support client (SupportTicket + SupportTicketMessage)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE support_ticket (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(150) NOT NULL, subject VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, access_token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_1F5A4D53A76ED395 (user_id), UNIQUE INDEX uniq_support_ticket_access_token (access_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_ticket_message (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, from_admin TINYINT NOT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, INDEX IDX_73251A5C700047D2 (ticket_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE support_ticket_message ADD CONSTRAINT FK_73251A5C700047D2 FOREIGN KEY (ticket_id) REFERENCES support_ticket (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D53A76ED395');
        $this->addSql('ALTER TABLE support_ticket_message DROP FOREIGN KEY FK_73251A5C700047D2');
        $this->addSql('DROP TABLE support_ticket');
        $this->addSql('DROP TABLE support_ticket_message');
    }
}
