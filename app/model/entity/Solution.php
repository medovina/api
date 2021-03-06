<?php

namespace App\Model\Entity;

use App\Helpers\EntityMetadata\Solution\SolutionParams;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use DateTime;
use Symfony\Component\Yaml\Yaml;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="solution_created_at_idx", columns={"created_at"})})
 * 
 * @method string getId()
 * @method Collection getFiles()
 * @method RuntimeEnvironment getRuntimeEnvironment()
 * @method DateTime getCreatedAt()
 * @method void setEvaluated(bool $evaluated)
 */
class Solution implements JsonSerializable
{
  use MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $author;

  public function getAuthor(): ?User {
    return $this->author->isDeleted() ? null : $this->author;
  }

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\OneToMany(targetEntity="SolutionFile", mappedBy="solution")
   */
  protected $files;

  /**
   * @ORM\ManyToOne(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironment;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $evaluated;

  /**
   * @ORM\Column(type="text")
   */
  protected $solutionParams;


  /**
   * @return array
   */
  public function jsonSerialize() {
    return [
      "userId" => $this->author->getId(),
      "createdAt" => $this->createdAt->getTimestamp(),
      "files" => $this->files->getValues()
    ];
  }

  /**
   * Constructor
   * @param User $author The user who submits the solution
   * @param RuntimeEnvironment $runtimeEnvironment
   */
  public function __construct(User $author, RuntimeEnvironment $runtimeEnvironment) {
    $this->author = $author;
    $this->files = new ArrayCollection();
    $this->evaluated = false;
    $this->createdAt = new DateTime();
    $this->runtimeEnvironment = $runtimeEnvironment;
    $this->solutionParams = "";
  }

  public function addFile(SolutionFile $file) {
    $this->files->add($file);
  }

  /**
   * Get names of the file which belongs to solution.
   * @return string[]
   */
  public function getFileNames(): array {
    return $this->files->map(function (SolutionFile $file) {
      return $file->getName();
    })->toArray();
  }

  public function getSolutionParams(): SolutionParams {
    return new SolutionParams(Yaml::parse($this->solutionParams));
  }

  public function setSolutionParams(SolutionParams $params) {
    $dumped = Yaml::dump($params->toArray());
    $this->solutionParams = $dumped ?: "";
  }

}
