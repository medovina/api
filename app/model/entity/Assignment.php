<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 */
class Assignment implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  private function __construct(
    string $name,
    string $description,
    DateTime $firstDeadline,
    int $maxPointsBeforeFirstDeadline,
    Exercise $exercise,
    Group $group,
    bool $isPublic,
    ArrayCollection $solutionRuntimeConfigs,
    int $submissionsCountLimit,
    bool $allowSecondDeadline,
    DateTime $secondDeadline = null,
    int $maxPointsBeforeSecondDeadline = 0
  ) {
    if ($secondDeadline == null) {
      $secondDeadline = $firstDeadline;
    }

    $this->name = $name;
    $this->description = $description;
    $this->exercise = $exercise;
    $this->group = $group;
    $this->firstDeadline = $firstDeadline;
    $this->maxPointsBeforeFirstDeadline = $maxPointsBeforeFirstDeadline;
    $this->allowSecondDeadline = $allowSecondDeadline;
    $this->secondDeadline = $secondDeadline;
    $this->maxPointsBeforeSecondDeadline = $maxPointsBeforeSecondDeadline;
    $this->submissions = new ArrayCollection;
    $this->isPublic = $isPublic;
    $this->solutionRuntimeConfigs = $solutionRuntimeConfigs;
    $this->submissionsCountLimit = $submissionsCountLimit;
    $this->scoreConfig = "";
  }

  public static function assignToGroup(Exercise $exercise, Group $group, $isPublic = FALSE) {
    $assignment = new self(
      $exercise->name,
      $exercise->assignment,
      new DateTime,
      0,
      $exercise,
      $group,
      $isPublic,
      $exercise->getSolutionRuntimeConfigs(),
      50,
      FALSE
    );

    $group->addAssignment($assignment);

    return $assignment;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  /**
   * @ORM\Column(type="smallint")
   */
  protected $submissionsCountLimit;

  /**
   * @ORM\ManyToMany(targetEntity="SolutionRuntimeConfig")
   */
  protected $solutionRuntimeConfigs;

  /**
   * @ORM\Column(type="text", nullable=true)
   */
  protected $scoreConfig;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $firstDeadline;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $allowSecondDeadline;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $secondDeadline;

  public function isAfterDeadline() {
    if ($this->allowSecondDeadline) {
      return $this->secondDeadline < new \DateTime;
    } else {
      return $this->firstDeadline < new \DateTime;
    }
  }

  /**
   * @ORM\Column(type="smallint")
   */
  protected $maxPointsBeforeFirstDeadline;

  /**
   * @ORM\Column(type="smallint")
   */
  protected $maxPointsBeforeSecondDeadline;

  public function getMaxPoints(DateTime $time = NULL) {
    if ($time === NULL || $time < $this->firstDeadline) {
      return $this->maxPointsBeforeFirstDeadline;
    } else if ($this->allowSecondDeadline && $time < $this->secondDeadline) {
      return $this->maxPointsBeforeSecondDeadline;
    } else {
      return 0;
    }
  }

  /**
   * @ORM\ManyToMany(targetEntity="LocalizedAssignment", cascade={"persist", "remove"})
   */
  protected $localizedAssignments;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise")
   */
  protected $exercise;

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="assignments")
   */
  protected $group;

  public function canReceiveSubmissions(User $user = NULL) {
    return $this->isPublic === TRUE &&
      $this->group->hasValidLicence() &&
      !$this->isAfterDeadline() &&
      ($user !== NULL && !$this->hasReachedSubmissionsCountLimit($user));
  }

  /**
   * Can a specific user access this assignment as student?
   */
  public function canAccessAsStudent(User $user) {
    return $this->isPublic === TRUE && $this->group->isStudentOf($user);
  }

  /**
   * Can a specific user access this assignment as supervisor?
   */
  public function canAccessAsSupervisor(User $user) {
    return $this->group->isSupervisorOf($user);
  }

  /**
   * @ORM\OneToMany(targetEntity="Submission", mappedBy="assignment")
   * @ORM\OrderBy({ "submittedAt" = "DESC" })
   */
  protected $submissions;

  public function getValidSubmissions(User $user) {
    $fromThatUser = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user))
      ->andWhere(Criteria::expr()->neq("resultsUrl", NULL));
    $validSubmissions = function ($submission) {
      if (!$submission->hasEvaluation()) {
        // the submission is not evaluated yet - suppose it will be evaluated in the future (or marked as invalid)
        // -> otherwise the user would be able to submit many solutions before they are evaluated
        return TRUE;
      }

      // keep only solutions, which are marked as valid (both manual and automatic way)
      $evaluation = $submission->getEvaluation();
      return ($evaluation->isValid() === TRUE && $evaluation->getEvaluationFailed() === FALSE);
    };

    return $this->submissions
      ->matching($fromThatUser)
      ->filter($validSubmissions);
  }

  public function hasReachedSubmissionsCountLimit(User $user) {
    return count($this->getValidSubmissions($user)) >= $this->submissionsCountLimit;
  }

  public function getLastSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user));
    return $this->submissions->matching($usersSolutions)->first();
  }

  public function getBestSolution(User $user) {
    $usersSolutions = Criteria::create()
      ->where(Criteria::expr()->eq("user", $user))
      ->andWhere(Criteria::expr()->neq("evaluation", NULL));

    return array_reduce(
      $this->submissions->matching($usersSolutions)->getValues(),
      function ($best, $submission) {
        if ($best === NULL) {
          return $submission;
        }

        return $submission->hasEvaluation() === FALSE || $best->getTotalPoints() > $submission->getTotalPoints()
          ? $best
          : $submission;
      },
      NULL
    );
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "isPublic" => $this->isPublic,
      "localizedAssignments" => $this->localizedAssignments->getValues(),
      "groupId" => $this->group->getId(),
      "firstDeadline" => $this->firstDeadline->getTimestamp(),
      "secondDeadline" => $this->secondDeadline->getTimestamp(),
      "allowSecondDeadline" => $this->allowSecondDeadline,
      "maxPointsBeforeFirstDeadline" => $this->maxPointsBeforeFirstDeadline,
      "maxPointsBeforeSecondDeadline" => $this->maxPointsBeforeSecondDeadline,
      "scoreConfig" => $this->scoreConfig,
      "submissionsCountLimit" => $this->submissionsCountLimit,
      "canReceiveSubmissions" => FALSE, // the app must perform a special request to get the valid information
      "solutionRuntimeConfigs" => $this->solutionRuntimeConfigs->map(function($config) { return $config->getId(); })->getValues()
    ];
  }
}