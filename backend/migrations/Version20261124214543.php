<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124214543 extends AbstractMigration
{
    /**
     * `doctrine:migrations:diff` remontait aussi la table `sessions` (gérée
     * hors ORM, cf. Version20260722020025) et deux colonnes sans rapport
     * (`course.type`, `system_setting.default_currency`, dérive de schéma
     * préexistante sur cette base locale) : retirées, seul `Offer::coverImage`
     * (miroir de `ProjectInfo::coverImage`, cf. Version20260730165238) reste.
     */
    public function getDescription(): string
    {
        return "Offer.coverImage : image de couverture réelle, comme ProjectInfo.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offer ADD cover_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873EE5A0E336 FOREIGN KEY (cover_image_id) REFERENCES media (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_29D6873EE5A0E336 ON offer (cover_image_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873EE5A0E336');
        $this->addSql('DROP INDEX IDX_29D6873EE5A0E336 ON offer');
        $this->addSql('ALTER TABLE offer DROP cover_image_id');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
