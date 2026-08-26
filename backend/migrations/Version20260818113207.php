<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lie UserSession à la LoginHistory créée au même instant (LoginListener::onLogin()) —
 * permet d'afficher la localisation déjà résolue par EnrichLoginLocationMessageHandler
 * sur l'écran /admin/security/sessions/ sans dupliquer l'appel de géolocalisation.
 *
 * Généré via doctrine:migrations:diff puis réduit à la main : le diff brut incluait
 * un DROP TABLE sessions (table PHP native non mappée en entité Doctrine — le
 * schema-diff la voit comme "en trop" et voudrait la supprimer, ce qui détruirait
 * toutes les sessions actives) ainsi que des changements sans rapport sur
 * course.type et system_setting.default_currency (dérive de schéma préexistante,
 * hors sujet ici).
 */
final class Version20260818113207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute UserSession.loginHistory (FK nullable vers login_history)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_session ADD login_history_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_8849CBDED3B21CEB FOREIGN KEY (login_history_id) REFERENCES login_history (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8849CBDED3B21CEB ON user_session (login_history_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY FK_8849CBDED3B21CEB');
        $this->addSql('DROP INDEX IDX_8849CBDED3B21CEB ON user_session');
        $this->addSql('ALTER TABLE user_session DROP login_history_id');
    }
}
