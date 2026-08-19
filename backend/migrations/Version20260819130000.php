<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Introduit App\Entity\Role (table role, 6 lignes fixes reflétant
 * role_hierarchy de security.yaml) et migre permission_definition.default_role
 * / current_role de colonnes string libres vers de vraies FK vers cette
 * table — cf. docblock de Role et PermissionDefinition. Ne touche ni à
 * User.roles (colonne JSON, source de vérité Symfony Security) ni à
 * role_hierarchy elle-même.
 */
final class Version20260819130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduit la table role et migre permission_definition.default_role/current_role en FK';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(30) NOT NULL, label VARCHAR(100) NOT NULL, `rank` INT NOT NULL, UNIQUE INDEX UNIQ_57698A6A77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO role (code, label, `rank`) VALUES
            ('ROLE_USER', 'Utilisateur', 0),
            ('ROLE_EDITOR', 'Éditeur', 1),
            ('ROLE_MODERATOR', 'Modérateur', 2),
            ('ROLE_MANAGER', 'Manager', 3),
            ('ROLE_ADMIN', 'Administrateur', 4),
            ('ROLE_SUPER_ADMIN', 'Super Administrateur', 5)");

        $this->addSql('ALTER TABLE permission_definition ADD default_role_id INT DEFAULT NULL, ADD current_role_id INT DEFAULT NULL');

        $this->addSql('UPDATE permission_definition pd INNER JOIN role r ON r.code = pd.default_role SET pd.default_role_id = r.id');
        $this->addSql('UPDATE permission_definition pd INNER JOIN role r ON r.code = pd.current_role SET pd.current_role_id = r.id');

        $this->addSql('ALTER TABLE permission_definition MODIFY default_role_id INT NOT NULL, MODIFY current_role_id INT NOT NULL');
        $this->addSql('ALTER TABLE permission_definition ADD CONSTRAINT FK_2C6467D5772C1BAE FOREIGN KEY (default_role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE permission_definition ADD CONSTRAINT FK_2C6467D5BCD57993 FOREIGN KEY (current_role_id) REFERENCES role (id)');
        $this->addSql('CREATE INDEX IDX_2C6467D5772C1BAE ON permission_definition (default_role_id)');
        $this->addSql('CREATE INDEX IDX_2C6467D5BCD57993 ON permission_definition (current_role_id)');

        $this->addSql('ALTER TABLE permission_definition DROP default_role, DROP current_role');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission_definition ADD default_role VARCHAR(30) DEFAULT NULL, ADD current_role VARCHAR(30) DEFAULT NULL');
        $this->addSql('UPDATE permission_definition pd INNER JOIN role r ON r.id = pd.default_role_id SET pd.default_role = r.code');
        $this->addSql('UPDATE permission_definition pd INNER JOIN role r ON r.id = pd.current_role_id SET pd.current_role = r.code');
        $this->addSql('ALTER TABLE permission_definition MODIFY default_role VARCHAR(30) NOT NULL, MODIFY current_role VARCHAR(30) NOT NULL');

        $this->addSql('ALTER TABLE permission_definition DROP FOREIGN KEY FK_2C6467D5772C1BAE');
        $this->addSql('ALTER TABLE permission_definition DROP FOREIGN KEY FK_2C6467D5BCD57993');
        $this->addSql('DROP INDEX IDX_2C6467D5772C1BAE ON permission_definition');
        $this->addSql('DROP INDEX IDX_2C6467D5BCD57993 ON permission_definition');
        $this->addSql('ALTER TABLE permission_definition DROP default_role_id, DROP current_role_id');

        $this->addSql('DROP TABLE role');
    }
}
