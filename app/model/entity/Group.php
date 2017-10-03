<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Gedmo\Mapping\Annotation as Gedmo;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(name="`group`")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method string getName()
 * @method DateTime getDeletedAt()
 * @method addAssignment(Assignment $assignment)
 * @method addChildGroup(Group $group)
 * @method Group getParentGroup()
 * @method string getExternalId()
 * @method string getDescription()
 */
class Group implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
      string $name,
      string $externalId,
      string $description,
      Instance $instance,
      User $admin = NULL,
      Group $parentGroup = NULL,
      bool $publicStats = TRUE,
      bool $isPublic = TRUE) {
    $this->name = $name;
    $this->externalId = $externalId;
    $this->description = $description;
    $this->memberships = new ArrayCollection;
    $this->admin = $admin;
    $this->instance = $instance;
    $this->publicStats = $publicStats;
    $this->isPublic = $isPublic;
    $this->childGroups = new ArrayCollection;
    $this->assignments = new ArrayCollection;
    if ($admin !== NULL) {
      $admin->makeSupervisorOf($this);
    }

    $this->parentGroup = $parentGroup;
    if ($parentGroup !== NULL) {
      $this->parentGroup->addChildGroup($this);
    }

    $instance->addGroup($this);
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
   * @ORM\Column(type="string", nullable=true)
   */
  protected $externalId;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\Column(type="float", nullable=true)
   */
  protected $threshold;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $publicStats;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  public function isPublic(): bool {
    return $this->isPublic;
  }

  public function isPrivate(): bool {
    return !$this->isPublic;
  }

  public function statsArePublic(): bool {
    return $this->publicStats;
  }

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $deletedAt;

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="childGroups")
   */
  protected $parentGroup;

  /**
   * @ORM\OneToMany(targetEntity="Group", mappedBy="parentGroup")
   */
  protected $childGroups;

  /**
   * Recursively merge all the subgroups into a flat array of groups.
   * @return array
   */
  public function getAllSubgroups() {
    $subtrees = $this->childGroups->map(function (Group $group) {
      return $group->getAllSubgroups();
    });
    return array_merge($this->childGroups->getValues(), ...$subtrees);
  }

  /**
   * @ORM\OneToMany(targetEntity="Exercise", mappedBy="group")
   */
  protected $exercises;

  public function getExercises() {
    return $this->exercises->filter(function (Exercise $exercise) {
      return $exercise->getDeletedAt() === NULL;
    });
  }

  /**
   * @ORM\ManyToOne(targetEntity="Instance", inversedBy="groups")
   */
  protected $instance;

  public function getInstance() {
    $group = $this;
    do {
      if ($group->instance) {
        return $group->instance;
      }
      $group = $group->parentGroup;
    } while ($group);

    return NULL;
  }

  public function hasValidLicence() {
    $instance = $this->getInstance();
    return $instance && $instance->hasValidLicence();
  }

  /**
   * @ORM\OneToMany(targetEntity="GroupMembership", mappedBy="group", cascade={"all"})
   */
  protected $memberships;

  protected function getMemberships() {
    return $this->memberships->filter(function (GroupMembership $membership) {
      return $membership->getUser()->getDeletedAt() === NULL;
    });
  }

  public function addMembership(GroupMembership $membership) {
    $this->memberships->add($membership);
  }

  public function removeMembership(GroupMembership $membership) {
    $this->getMemberships()->remove($membership);
  }

  protected function getActiveMemberships() {
    $filter = Criteria::create()
                ->where(Criteria::expr()->eq("status", GroupMembership::STATUS_ACTIVE));

    return $this->getMemberships()->matching($filter);
  }

  /**
   * Return all active members depending on specified type
   */
  protected function getActiveMembers(string $type) {
    if ($type == GroupMembership::TYPE_ALL) {
      $members = $this->getActiveMemberships();
    } else {
      $filter = Criteria::create()->where(Criteria::expr()->eq("type", $type));
      $members = $this->getActiveMemberships()->matching($filter);
    }

    return $members->map(
      function(GroupMembership $membership) {
        return $membership->getUser();
      }
    );
  }

  public function getStudents() {
    return $this->getActiveMembers(GroupMembership::TYPE_STUDENT);
  }

  public function isStudentOf(User $user) {
    return $this->getStudents()->contains($user);
  }

  public function getSupervisors() {
    return $this->getActiveMembers(GroupMembership::TYPE_SUPERVISOR);
  }

  public function isSupervisorOf(User $user) {
    return $this->getSupervisors()->contains($user);
  }

  public function isMemberOf(User $user) {
    return $this->getActiveMembers(GroupMembership::TYPE_ALL)->contains($user);
  }

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $admin;

  public function getAdminsIds() {
    $group = $this;
    $admins = [];
    while ($group !== NULL) {
      if ($group->admin !== NULL) {
        $admins[] = $group->admin->getId();
      }

      $group = $group->parentGroup;
    }

    return array_values(array_unique($admins));
  }

  /**
   * User is admin of a group when he is
   * @param User $user
   * @return bool
   */
  public function isAdminOf(User $user) {
    $admins = $this->getAdminsIds();
    return array_search($user->getId(), $admins, TRUE) !== FALSE;
  }

  public function makeAdmin(User $user) {
    $this->admin = $user;
  }

  /**
   * @ORM\OneToMany(targetEntity="Assignment", mappedBy="group")
   */
  protected $assignments;

  /**
   * Map collection of assignments to an array of its ID's
   * @param ArrayCollection   $assignments  List of assignments
   * @return string[]
   */
  public function getAssignmentsIds($assignments = NULL): array {
    $assignments = $assignments === NULL ? $this->getAssignments() : $assignments;
    return $assignments->map(function (Assignment $a) {
      return $a->getId();
    })->getValues();
  }

  public function getAssignments() {
    return $this->assignments->filter(function (Assignment $assignment) {
      return $assignment->getDeletedAt() === NULL;
    });
  }

  public function getMaxPoints(): int {
    return array_reduce(
      $this->getAssignments()->getValues(),
      function ($carry, Assignment $assignment) { return $carry + $assignment->getGroupPoints(); },
      0
    );
  }

  public function getBestSolutions(User $user): array {
    return $this->getAssignments()->map(
      function (Assignment $assignment) use ($user) {
        return $assignment->getBestSolution($user);
      }
    )->getValues();
  }

  public function getCompletedAssignmentsByStudent(User $student) {
    return $this->getAssignments()->filter(
      function(Assignment $assignment) use ($student) {
        return $assignment->getBestSolution($student) !== NULL;
      }
    );
  }

  public function getMissedAssignmentsByStudent(User $student) {
    return $this->getAssignments()->filter(
      function(Assignment $assignment) use ($student) {
        return $assignment->isAfterDeadline() && $assignment->getBestSolution($student) === NULL;
      }
    );
  }

  public function getPointsGainedByStudent(User $student) {
    return array_reduce(
      $this->getCompletedAssignmentsByStudent($student)->getValues(),
      function ($carry, Assignment $assignment) use ($student) {
        $best = $assignment->getBestSolution($student);
        if ($best !== NULL) {
          $carry += $best->getTotalPoints();
        }

        return $carry;
      },
      0
    );
  }

  /**
   * Get the statistics of an individual student.
   * @param User $student   Student of this group
   * @return array          Students statistics
   */
  public function getStudentsStats(User $student) {
    $total = $this->getAssignments()->count();
    $completed = $this->getCompletedAssignmentsByStudent($student);
    $missed = $this->getMissedAssignmentsByStudent($student);
    $maxPoints = $this->getMaxPoints();
    $gainedPoints = $this->getPointsGainedByStudent($student);

    $statuses = [];
    /** @var Assignment $assignment */
    foreach ($this->getAssignments() as $assignment) {
      $best = $assignment->getBestSolution($student);
      $solution = $best ? $best : $assignment->getLastSolution($student);
      $statuses[$assignment->getId()] = $solution ? $solution->getEvaluationStatus() : NULL;
    }

    return [
      "userId" => $student->getId(),
      "groupId" => $this->id,
      "assignments" => [
        "total" => $total,
        "completed" => $completed->count(),
        "missed" => $missed->count()
      ],
      "points" => [
        "total" => $maxPoints,
        "gained" => $gainedPoints
      ],
      "statuses" => $statuses,
      "hasLimit" => $this->threshold !== NULL && $this->threshold > 0,
      "passesLimit" => $this->threshold === NULL ? TRUE : $gainedPoints >= $maxPoints * $this->threshold
    ];
  }

  /**
   * Student can view only public assignments.
   */
  public function getPublicAssignments() {
    return $this->getAssignments()->filter(
      function (Assignment $assignment) { return $assignment->isPublic(); }
    );
  }

  /**
   * Get identifications of groups in descending order.
   * @return string[]
   */
  public function getParentGroupsIds(): array {
    $group = $this->getParentGroup();
    $parents = [];
    while ($group !== NULL) {
      $parents[] = $group->getId();
      $group = $group->getParentGroup();
    }

    return array_values(array_reverse($parents));
  }

  /**
   * Get names of parent groups in descending order.
   * @return string[]
   */
  public function getParentGroupsNames(): array {
    $group = $this->getParentGroup();
    $parentsNames = [];
    while ($group !== NULL) {
      $parentsNames[] = $group->getName();
      $group = $group->getParentGroup();
    }

    return array_values(array_reverse($parentsNames));
  }

  public function jsonSerialize() {
    $instance = $this->getInstance();
    return [
      "id" => $this->id,
      "externalId" => $this->externalId,
      "name" => $this->name,
      "description" => $this->description,
      "adminId" => $this->admin ? $this->admin->getId() : NULL,
      "admins" => $this->getAdminsIds(),
      "supervisors" => $this->getSupervisors()->map(function(User $s) { return $s->getId(); })->getValues(),
      "students" => $this->getStudents()->map(function(User $s) { return $s->getId(); })->getValues(),
      "instanceId" => $instance ? $instance->getId() : NULL,
      "hasValidLicence" => $this->hasValidLicence(),
      "parentGroupId" => $this->parentGroup ? $this->parentGroup->getId() : NULL,
      "parentGroupsIds" => $this->getParentGroupsIds(),
      "childGroups" => [
        "all" => $this->getChildGroups()->map(
          function(Group $group) {
            return $group->getId();
          }
        )->getValues(),
        "public" => $this->getChildGroups()->filter(
          function(Group $g) {
            return $g->isPublic();
          }
        )->map(
          function(Group $group) {
            return $group->getId();
          }
        )->getValues()
      ],
      "assignments" => [
        "all" => $this->getAssignmentsIds(),
        "public" => $this->getAssignmentsIds($this->getPublicAssignments())
      ],
      "publicStats" => $this->publicStats,
      "isPublic" => $this->isPublic,
      "threshold" => $this->threshold
    ];
  }

  public function getChildGroups() {
    return $this->childGroups->filter(function (Group $group) {
      return $group->getDeletedAt() === NULL;
    });
  }
}
