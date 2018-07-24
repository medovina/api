<?php
namespace App\Model\Entity;
use JsonSerializable;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * @ORM\Entity
 * @method Solution getSolution()
 * @method string getFileServerPath()
 */
class SolutionFile extends UploadedFile implements JsonSerializable {
  use MagicAccessors;

  /**
   * @ORM\ManyToOne(targetEntity="Solution")
   * @ORM\JoinColumn(onDelete="SET NULL")
   */
  protected $solution;

  /**
   * @ORM\Column(type="string")
   */
  protected $fileServerPath;

  public function __construct($name, DateTime $uploadedAt, $fileSize, User $user, $filePath, Solution $solution) {
    parent::__construct($name, $uploadedAt, $fileSize, $user, $filePath);
    $this->solution = $solution;
    $solution->addFile($this);
  }

  public static function fromUploadedFile(UploadedFile $file, Solution $solution) {
    return new self(
      $file->getName(),
      $file->getUploadedAt(),
      $file->getFileSize(),
      $file->user, // We avoid the getter here to bypass getting null for soft-deleted users
      $file->getLocalFilePath(),
      $solution
    );
  }

  public function removeLocalFile() {
    unlink($this->localFilePath);
    $this->localFilePath = null;
  }
}
