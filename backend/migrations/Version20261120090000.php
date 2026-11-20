<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Révocation des jetons (audit 2026-11-20, P1 #8) :
 * - user.token_version : incrémenté à chaque changement de mot de passe /
 *   déconnexion forcée, vérifié sur chaque JWT (claim `tv`) et session web ;
 * - cache_items : stockage en base (Symfony DoctrineDbalAdapter) de la liste
 *   de blocage JWT (POST /api/logout) et des compteurs de délai de connexion
 *   (App\Security\LoginThrottle) — doit survivre aux redéploiements.
 *
 * Isolé à la main du diff (drift sans rapport sessions/course/system_setting).
 */
final class Version20261120090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.token_version et la table cache_items (blocklist JWT, délai de connexion).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD token_version INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE cache_items (item_id VARBINARY(255) NOT NULL, item_data MEDIUMBLOB NOT NULL, item_lifetime INT UNSIGNED DEFAULT NULL, item_time INT UNSIGNED NOT NULL, PRIMARY KEY (item_id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cache_items');
        $this->addSql('ALTER TABLE user DROP token_version');
    }
}
