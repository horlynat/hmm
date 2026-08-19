<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818152356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute invoice.created_by_id / marked_paid_by_id — traçabilité SoD (cf. Invoice::wasCreatedAndMarkedPaidBySamePerson()).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice ADD created_by_id INT DEFAULT NULL, ADD marked_paid_by_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              invoice
            ADD
              CONSTRAINT FK_90651744B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              invoice
            ADD
              CONSTRAINT FK_906517441D7D7FB4 FOREIGN KEY (marked_paid_by_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_90651744B03A8386 ON invoice (created_by_id)');
        $this->addSql('CREATE INDEX IDX_906517441D7D7FB4 ON invoice (marked_paid_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744B03A8386');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517441D7D7FB4');
        $this->addSql('DROP INDEX IDX_90651744B03A8386 ON invoice');
        $this->addSql('DROP INDEX IDX_906517441D7D7FB4 ON invoice');
        $this->addSql('ALTER TABLE invoice DROP created_by_id, DROP marked_paid_by_id');
    }
}
