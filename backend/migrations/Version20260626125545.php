<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626125545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tag_article DROP FOREIGN KEY `FK_300B23CC7294869C`');
        $this->addSql('ALTER TABLE tag_article DROP FOREIGN KEY `FK_300B23CCBAD26311`');
        $this->addSql('DROP TABLE tag_article');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tag_article (tag_id INT NOT NULL, article_id INT NOT NULL, INDEX IDX_300B23CC7294869C (article_id), INDEX IDX_300B23CCBAD26311 (tag_id), PRIMARY KEY (tag_id, article_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE tag_article ADD CONSTRAINT `FK_300B23CC7294869C` FOREIGN KEY (article_id) REFERENCES article (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag_article ADD CONSTRAINT `FK_300B23CCBAD26311` FOREIGN KEY (tag_id) REFERENCES tag (id) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
