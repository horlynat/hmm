<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute newsletter_subscriber.confirmed_at (double opt-in, cf. App\Entity\
 * NewsletterSubscriber). Le diff auto-généré incluait à nouveau la même
 * dérive de schéma locale préexistante sans rapport (DROP TABLE sessions,
 * 2 ALTER TABLE) — cf. Version20260825125951 pour le précédent. Exclue.
 */
final class Version20260825134741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute newsletter_subscriber.confirmed_at (double opt-in newsletter).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE newsletter_subscriber ADD confirmed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE newsletter_subscriber DROP confirmed_at');
    }
}
