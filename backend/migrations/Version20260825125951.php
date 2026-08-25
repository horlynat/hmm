<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table pour App\Entity\NewsletterSubscriber — inscriptions à la newsletter
 * du blog (cf. son docblock : jusqu'ici un formulaire purement visuel, sans
 * persistance réelle derrière).
 *
 * Le diff auto-généré incluait aussi DROP TABLE sessions et deux ALTER TABLE
 * (course, system_setting) — une dérive de schéma locale préexistante, sans
 * rapport avec cette fonctionnalité (constatée sur CETTE base de dev,
 * probablement en retard sur des migrations déjà mergées ailleurs).
 * Volontairement exclue d'ici : une migration doit correspondre à un seul
 * changement logique, jamais embarquer un DROP TABLE sans rapport en passager
 * clandestin.
 */
final class Version20260825125951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute newsletter_subscriber (inscriptions à la newsletter du blog).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE newsletter_subscriber (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, locale VARCHAR(5) NOT NULL, subscribed_at DATETIME NOT NULL, unsubscribed_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_401562C3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_subscriber');
    }
}
