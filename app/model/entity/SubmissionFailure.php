<?php
namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;

/**
 * @ORM\Entity
 */
class SubmissionFailure implements JsonSerializable {

  use MagicAccessors;

  /**
   * Broker rejected the submission. This happens when there is no worker who can evaluate it.
   */
  const TYPE_BROKER_REJECT = "broker_reject";

  /**
   * Evaluation failed after the job has been accepted.
   */
  const TYPE_EVALUATION_FAILURE = "evaluation_failure";

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $type;

  /**
   * @ORM\Column(type="string")
   */
  protected $description;

  /**
   * @ORM\ManyToOne(targetEntity="Submission")
   */
  protected $submission;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $resolvedAt;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $resolutionNote;

  public function __construct(string $type, string $description, Submission $submission, DateTime $createdAt = NULL) {
    $this->type = $type;
    $this->description = $description;
    $this->submission = $submission;
    $this->createdAt = $createdAt ?: new DateTime();
  }

  public function resolve(string $note, DateTime $resolvedAt = NULL) {
    $this->resolvedAt = $resolvedAt ?: new DateTime();
    $this->resolutionNote = $note;
  }

  function jsonSerialize() {
    return [
      "type" => $this->type,
      "description" => $this->description,
      "submission" => $this->submission->id,
      "createdAt" => $this->createdAt->getTimestamp(),
      "resolvedAt" => $this->resolvedAt ? $this->resolvedAt->getTimestamp() : NULL,
      "resolutionNote" => $this->resolutionNote
    ];
  }
}