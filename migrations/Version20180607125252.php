<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180607125252 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // Remove the part of url just before the third occurence of "/" - coincidentally, this is the scheme and host name
        $this->addSql("UPDATE uploaded_file
          SET file_server_path = RIGHT(file_server_path, LENGTH(file_server_path) - LENGTH(SUBSTRING_INDEX(file_server_path, '/', 3))) 
          WHERE `file_server_path` IS NOT NULL
          AND discriminator = 'supplementaryexercisefile'
        ");

        // Set fileserver paths for assignment solution files
        $this->addSql("UPDATE uploaded_file f SET f.file_server_path = CONCAT('/submissions/student_', f.solution_id, '/', f.`name`) WHERE f.discriminator = 'solutionfile' AND f.file_server_path IS NULL AND EXISTS(SELECT a.id FROM assignment_solution a WHERE a.solution_id = f.solution_id)");

        // Set fileserver paths for reference solution files
        $this->addSql("UPDATE uploaded_file f SET f.file_server_path = CONCAT('/submissions/reference_', f.solution_id, '/', f.`name`) WHERE f.discriminator = 'solutionfile' AND f.file_server_path IS NULL AND EXISTS(SELECT r.id FROM reference_exercise_solution r WHERE r.solution_id = f.solution_id)");
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
      $this->throwIrreversibleMigrationException();
    }
}
