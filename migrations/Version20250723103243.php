<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723103243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent_connexion (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, numagent VARCHAR(5) NOT NULL, type VARCHAR(15) NOT NULL, ip VARCHAR(15) DEFAULT NULL, mac VARCHAR(17) DEFAULT NULL, dateconnexion DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, dateactualisation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_643F7FD84AD0E568 FOREIGN KEY (numagent) REFERENCES agent (numagent) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_agent_connexion_numagent ON agent_connexion (numagent)');
        $this->addSql('CREATE INDEX idx_agent_connexion_type ON agent_connexion (type)');
        $this->addSql('ALTER TABLE syslog RENAME TO log');
        $this->addSql('ALTER TABLE agent_position ADD COLUMN dateexpiration DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_position DROP COLUMN ip');
        $this->addSql('ALTER TABLE agent_position DROP COLUMN dateactualisation');
        $this->addSql('ALTER TABLE systemevents DROP COLUMN syslogtag');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE syslog (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, timestamp DATETIME NOT NULL, message VARCHAR(255) NOT NULL COLLATE "BINARY", switch_id INTEGER NOT NULL, CONSTRAINT FK_C142E2D7BE2FFB85 FOREIGN KEY (switch_id) REFERENCES network_switch (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C142E2D7BE2FFB85 ON syslog (switch_id)');
        $this->addSql('DROP TABLE agent_connexion');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TEMPORARY TABLE __temp__agent_position AS SELECT jour, dateconnexion, numagent, position_id FROM agent_position');
        $this->addSql('DROP TABLE agent_position');
        $this->addSql('CREATE TABLE agent_position (jour DATE NOT NULL, dateconnexion DATETIME NOT NULL, numagent VARCHAR(5) NOT NULL, position_id INTEGER NOT NULL, dateactualisation DATETIME NOT NULL, ip VARCHAR(15) NOT NULL, PRIMARY KEY(numagent), CONSTRAINT FK_70FDFD614AD0E568 FOREIGN KEY (numagent) REFERENCES agent (numagent) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_70FDFD61DD842E46 FOREIGN KEY (position_id) REFERENCES position (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO agent_position (jour, dateconnexion, numagent, position_id) SELECT jour, dateconnexion, numagent, position_id FROM __temp__agent_position');
        $this->addSql('DROP TABLE __temp__agent_position');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70FDFD61DD842E46 ON agent_position (position_id)');
        $this->addSql('ALTER TABLE systemevents ADD COLUMN syslogtag VARCHAR(60) DEFAULT NULL');
    }
}
