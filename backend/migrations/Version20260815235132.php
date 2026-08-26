<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815235132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index unique sur invoice.number — InvoiceType::number est un champ texte libre saisi à la main, sans aucune garde d\'unicité jusqu\'ici (risque de doublon silencieux).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9065174496901F54 ON invoice (number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_9065174496901F54 ON invoice');
    }
}
