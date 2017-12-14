<?php

namespace App\Model\Entity;

use App\Helpers\Localizations;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use JsonSerializable;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method Group getRootGroup()
 * @method setAdmin(User $admin)
 */
class Instance implements JsonSerializable
{
  use MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isOpen;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isAllowed;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $updatedAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $deletedAt;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $admin;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $needsLicence;

  /**
   * @ORM\ManyToOne(targetEntity="Group", cascade={"persist"})
   * @var Group
   */
  protected $rootGroup;

  /**
   * @var ArrayCollection
   * @ORM\OneToMany(targetEntity="Licence", mappedBy="instance")
   */
  protected $licences;

  public function addLicence(Licence $licence)
  {
    $this->licences->add($licence);
  }

  public function getValidLicences() {
    return $this->licences->filter(function ($licence) {
      return $licence->isValid();
    });
  }

  public function hasValidLicence() {
    return $this->needsLicence === FALSE || $this->getValidLicences()->count() > 0;
  }

  /**
   * @ORM\OneToMany(targetEntity="User", mappedBy="instance")
   */
  protected $members;

  /**
   * Get members of the instance with given filter applied.
   * @param null $search
   * @return User[]
   */
  public function getMembers($search = NULL) {
    if ($search === NULL || empty($search)) {
      return $this->members->filter(function (User $user) {
        return $user->getDeletedAt() === NULL;
      })->toArray();
    }

    $filter = Criteria::create()->where(Criteria::expr()->andX(
      Criteria::expr()->isNull("deletedAt"),
      Criteria::expr()->orX(
        Criteria::expr()->contains("firstName", $search),
        Criteria::expr()->contains("lastName", $search)
      )
    ));
    $members = $this->members->matching($filter);
    if ($members->count() > 0) {
      return $members->toArray();
    }

    // weaker filter - the strict one did not match anything
    $members = [];
    foreach (explode(" ", $search) as $part) {
      // skip empty parts
      $part = trim($part);
      if (empty($part)) {
        continue;
      }

      $filter = Criteria::create()->where(Criteria::expr()->andX(
        Criteria::expr()->isNull("deletedAt"),
        Criteria::expr()->orX(
          Criteria::expr()->contains("firstName", $part),
          Criteria::expr()->contains("lastName", $part)
        )
      ));
      $members = array_merge($members, $this->members->matching($filter)->toArray());
    }

    return $members;
  }

  public function addMember(User $user) {
    $this->members->add($user);
  }

  /**
   * @ORM\OneToMany(targetEntity="Group", mappedBy="instance", cascade={"persist"})
   */
  protected $groups;

  public function addGroup(Group $group) {
    $this->groups->add($group);
  }

  public function getGroups() {
    return $this->groups->filter(function ($group) {
      return $group->getDeletedAt() === NULL;
    });
  }

  public function isAllowed() {
    return $this->isAllowed;
  }

  public function getData(User $user = NULL) {
    /** @var LocalizedGroup $localizedRootGroup */
    $localizedRootGroup = Localizations::getPrimaryLocalization($this->rootGroup->getLocalizedTexts());

    return [
      "id" => $this->id,
      "name" => $localizedRootGroup ? $localizedRootGroup->getName() : "",
      "description" => $localizedRootGroup ? $localizedRootGroup->getDescription() : "",
      "hasValidLicence" => $this->hasValidLicence(),
      "isOpen" => $this->isOpen,
      "isAllowed" => $this->isAllowed,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "deletedAt" => $this->deletedAt ? $this->deletedAt->getTimestamp() : NULL,
      "admin" => $this->admin ? $this->admin->getId() : NULL,
      "rootGroupId" => $this->rootGroup !== NULL ? $this->rootGroup->getId() : NULL
    ];
  }

  public function jsonSerialize() {
    return $this->getData(NULL);
  }

  public function __construct(){
    $this->licences = new ArrayCollection();
    $this->groups = new ArrayCollection();
    $this->members = new ArrayCollection();
  }

  public function getName() {
    /** @var LocalizedGroup $localizedRootGroup */
    $localizedRootGroup = Localizations::getPrimaryLocalization($this->rootGroup->getLocalizedTexts());
    return $localizedRootGroup->getName();
  }

  public static function createInstance(array $localizedTexts, bool $isOpen, User $admin = NULL) {
    $instance = new Instance;
    $instance->isOpen = $isOpen;
    $instance->isAllowed = TRUE; //@todo - find out who should set this and how
    $instance->needsLicence = TRUE;
    $now = new \DateTime;
    $instance->createdAt = $now;
    $instance->updatedAt = $now;
    $instance->admin = $admin;

    // now create the root group for the instance
    $instance->rootGroup = new Group(
      "",
      $instance,
      $admin,
      NULL,
      FALSE,
      TRUE
    );

    /** @var LocalizedGroup $text */
    foreach ($localizedTexts as $text) {
      $instance->rootGroup->addLocalizedText($text);
    }

    return $instance;
  }

}
