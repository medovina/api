<?php
namespace App\Console;

use App\Helpers\UploadsConfig;
use App\Model\Repository\UploadedFiles;
use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupUploads extends Command {
  /**
   * @var UploadsConfig
   */
  private $uploadsConfig;

  /**
   * @var UploadedFiles
   */
  private $uploadedFiles;

  public function __construct(UploadsConfig $config, UploadedFiles $uploadedFiles) {
    parent::__construct();
    $this->uploadsConfig = $config;
    $this->uploadedFiles = $uploadedFiles;
  }

  protected function configure() {
    $this->setName('uploads:cleanup')->setDescription('Remove unused uploaded files.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $now = new DateTime();
    $unused = $this->uploadedFiles->findUnused($now, $this->uploadsConfig->getRemovalThreshold());

    foreach ($unused as $file) {
      $this->uploadedFiles->remove($file);
    }

    $output->writeln(sprintf("Removed %d unused files", count($unused)));
    return 0;
  }
}
