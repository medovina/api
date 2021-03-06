<?php

namespace App\Model\Entity;

use App\Exceptions\ExerciseConfigException;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use DateTime;

/**
 * @ORM\Entity
 * @method string getId()
 * @method string getLimits()
 * @method RuntimeEnvironment getRuntimeEnvironment()
 * @method HardwareGroup getHardwareGroup()
 */
class ExerciseLimits implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="text")
   */
  protected $limits;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\ManyToOne(targetEntity="ExerciseLimits")
   * @ORM\JoinColumn(onDelete="SET NULL")
   */
  protected $createdFrom;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $author;

  public function getAuthor(): ?User {
    return $this->author->isDeleted() ? null : $this->author;
  }

  /**
   * @ORM\ManyToOne(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironment;

  /**
   * @ORM\ManyToOne(targetEntity="HardwareGroup")
   */
  protected $hardwareGroup;

  /**
   * Constructor
   * @param RuntimeEnvironment $runtimeEnvironment
   * @param HardwareGroup $hardwareGroup
   * @param string $limits
   * @param User $author
   * @param ExerciseLimits|null $createdFrom
   */
  public function __construct(RuntimeEnvironment $runtimeEnvironment,
      HardwareGroup $hardwareGroup, string $limits, User $author,
      ExerciseLimits $createdFrom = null) {
    $this->runtimeEnvironment = $runtimeEnvironment;
    $this->hardwareGroup = $hardwareGroup;
    $this->limits = $limits;
    $this->createdAt = new DateTime();
    $this->createdFrom = $createdFrom;
    $this->author = $author;
  }

  /**
   * Return array-like structure containing limits.
   * @return array|string
   * @throws ExerciseConfigException
   */
  public function getParsedLimits() {
    try {
      return Yaml::parse($this->limits);
    } catch (ParseException $e) {
      throw new ExerciseConfigException("Exercise limits configuration is not a valid YAML and it cannot be parsed.");
    }
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "runtimeConfigId" => $this->runtimeEnvironment->getId(),
      "hardwareGroupId" => $this->hardwareGroup->getId(),
      "limits" => $this->limits,
      "createdAt" => $this->createdAt->getTimestamp(),
      "createdFrom" => $this->createdFrom ? $this->createdFrom->id : ""
    ];
  }

  public function equals(?ExerciseLimits $other): bool {
    if ($other === null) {
      return false;
    }

    try {
      return Yaml::dump($this->getParsedLimits()) === Yaml::dump($other->getParsedLimits());
    } catch (ExerciseConfigException $exception) {
      return false;
    }
  }

}
