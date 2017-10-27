<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171025183047 extends AbstractMigration {
  /**
   * @param Schema $schema
   */
  public function up(Schema $schema) {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    // Boring stuff related to renaming indices and foreign keys
    $this->addSql('ALTER TABLE exercise_localized_exercise DROP FOREIGN KEY FK_8327FD4EA9B14E11');
    $this->addSql('DROP INDEX IDX_8327FD4EA9B14E11 ON exercise_localized_exercise');
    $this->addSql('ALTER TABLE exercise_localized_exercise DROP PRIMARY KEY');
    $this->addSql('ALTER TABLE exercise_localized_exercise DROP FOREIGN KEY FK_8327FD4EE934951A');
    $this->addSql('ALTER TABLE exercise_localized_exercise CHANGE localized_text_id localized_exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
    $this->addSql('ALTER TABLE exercise_localized_exercise ADD CONSTRAINT FK_98A84F90EF02E9CC FOREIGN KEY (localized_exercise_id) REFERENCES localized_exercise (id) ON DELETE CASCADE');
    $this->addSql('CREATE INDEX IDX_98A84F90EF02E9CC ON exercise_localized_exercise (localized_exercise_id)');

    $this->addSql('ALTER TABLE exercise_localized_exercise ADD PRIMARY KEY (exercise_id, localized_exercise_id)');
    $this->addSql('DROP INDEX idx_8327fd4ee934951a ON exercise_localized_exercise');
    $this->addSql('CREATE INDEX IDX_98A84F90E934951A ON exercise_localized_exercise (exercise_id)');
    $this->addSql('ALTER TABLE exercise_localized_exercise ADD CONSTRAINT FK_8327FD4EE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE');

    $this->addSql('ALTER TABLE localized_exercise DROP FOREIGN KEY FK_7C6ACF3E3EA4CB4D');
    $this->addSql('DROP INDEX idx_7c6acf3e3ea4cb4d ON localized_exercise');
    $this->addSql('CREATE INDEX IDX_BCAD00373EA4CB4D ON localized_exercise (created_from_id)');
    $this->addSql('ALTER TABLE localized_exercise ADD CONSTRAINT FK_7C6ACF3E3EA4CB4D FOREIGN KEY (created_from_id) REFERENCES localized_exercise (id)');

    $this->addSql('ALTER TABLE assignment_localized_exercise DROP FOREIGN KEY FK_9C8F78CA9B14E11');
    $this->addSql('DROP INDEX IDX_9C8F78CA9B14E11 ON assignment_localized_exercise');
    $this->addSql('ALTER TABLE assignment_localized_exercise DROP PRIMARY KEY');
    $this->addSql('ALTER TABLE assignment_localized_exercise DROP FOREIGN KEY FK_9C8F78CD19302F8');
    $this->addSql('ALTER TABLE assignment_localized_exercise CHANGE localized_text_id localized_exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
    $this->addSql('ALTER TABLE assignment_localized_exercise ADD CONSTRAINT FK_9DF069D6EF02E9CC FOREIGN KEY (localized_exercise_id) REFERENCES localized_exercise (id) ON DELETE CASCADE');
    $this->addSql('CREATE INDEX IDX_9DF069D6EF02E9CC ON assignment_localized_exercise (localized_exercise_id)');

    $this->addSql('ALTER TABLE assignment_localized_exercise ADD PRIMARY KEY (assignment_id, localized_exercise_id)');
    $this->addSql('DROP INDEX idx_9c8f78cd19302f8 ON assignment_localized_exercise');
    $this->addSql('CREATE INDEX IDX_9DF069D6D19302F8 ON assignment_localized_exercise (assignment_id)');
    $this->addSql('ALTER TABLE assignment_localized_exercise ADD CONSTRAINT FK_9C8F78CD19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE');

    // The interesting part - moving name and description to localized entities
    $this->addSql('ALTER TABLE localized_exercise ADD description LONGTEXT NOT NULL DEFAULT "", CHANGE short_text `name` LONGTEXT NOT NULL DEFAULT "", CHANGE text assignment_text LONGTEXT NOT NULL');

    // Copy description from most recently updated related exercise (there cannot be any because we just added the column)
    $this->addSql('UPDATE TABLE localized_exercise l
                       INNER JOIN exercise e ON (e.id = (
                         SELECT id FROM exercise e2 INNER JOIN exercise_localized_exercise el ON el.localized_exercise_id = l.id ORDER BY e2.updated_at DESC LIMIT 1
                       )) 
                       SET description = e.description');

    // Copy name from most recently updated related exercise if there is no name yet
    $this->addSql('UPDATE TABLE localized_exercise l SET `name` = e.name WHERE l.name = ""');

    // Copy name from most recently updated related assignment if there is no name yet (the condition won't hold if we managed to copy something from an exercise)
    $this->addSql('UPDATE TABLE localized_exercise l
                       INNER JOIN assignment a ON (e.id = (
                         SELECT id FROM assignment a2 INNER JOIN assignment_localized_exercise al ON al.localized_exercise_id = l.id ORDER BY a2.updated_at DESC LIMIT 1
                       )) 
                       SET `name` = a.name
                       WHERE l.`name` = ""');

    // Drop name and description - we don't need them anymore
    $this->addSql('ALTER TABLE exercise DROP `name`, DROP description');
    $this->addSql('ALTER TABLE assignment DROP `name`');
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema) {
    $this->throwIrreversibleMigrationException();
  }
}
