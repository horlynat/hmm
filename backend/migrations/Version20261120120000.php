<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Changement d'e-mail self-service en deux temps (audit 2026-11-20, P1 #7) :
 * la nouvelle adresse est stockée en attente jusqu'à confirmation cliquée
 * depuis cette même nouvelle adresse (cf. App\Controller\
 * ProfileEmailChangeController, App\Entity\User::requestEmailChange()).
 *
 * Isolé à la main du diff (drift sans rapport sessions/course/system_setting,
 * déjà couvert par d'autres migrations existantes).
 */
final class Version20261120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute user.pending_email et user.email_change_requested_at (changement d'e-mail en deux temps).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD pending_email VARCHAR(180) DEFAULT NULL, ADD email_change_requested_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP pending_email, DROP email_change_requested_at');
    }
}
