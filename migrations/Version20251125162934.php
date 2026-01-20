<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251125162934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transaction DROP CONSTRAINT fk_723705d17e3c61f9');
        $this->addSql('DROP INDEX idx_723705d17e3c61f9');
        $this->addSql('ALTER TABLE transaction RENAME COLUMN owner_id TO user_id');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_723705D1A76ED395 ON transaction (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transaction DROP CONSTRAINT FK_723705D1A76ED395');
        $this->addSql('DROP INDEX IDX_723705D1A76ED395');
        $this->addSql('ALTER TABLE transaction RENAME COLUMN user_id TO owner_id');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT fk_723705d17e3c61f9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_723705d17e3c61f9 ON transaction (owner_id)');
    }
}
