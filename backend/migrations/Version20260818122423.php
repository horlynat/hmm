<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée la table blocked_ip (cf. App\Entity\BlockedIp, App\EventSubscriber\IpBlockSubscriber).
 *
 * Généré via doctrine:migrations:diff puis réduit à la main : le diff brut
 * incluait à nouveau un DROP TABLE sessions (table PHP native non mappée en
 * entité Doctrine — cf. Version20260818113207) ainsi que des changements sans
 * rapport sur course.type et system_setting.default_currency (dérive de
 * schéma préexistante, hors sujet ici).
 */
final class Version20260818122423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée blocked_ip (blocage manuel de tentatives de connexion par IP)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE blocked_ip (
              id INT AUTO_INCREMENT NOT NULL,
              ip VARCHAR(45) NOT NULL,
              reason VARCHAR(255) NOT NULL,
              blocked_by_label VARCHAR(180) DEFAULT NULL,
              expires_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_blocked_ip_address (ip),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE blocked_ip');
    }
}
