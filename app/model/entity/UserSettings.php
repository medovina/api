<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 *
 * @method bool getDarkTheme()
 * @method bool getVimMode()
 * @method bool getOpenedSidebar()
 * @method bool getUseGravatar()
 * @method ?string getDefaultPage()
 * @method string getDefaultLanguage()
 * @method bool getNewAssignmentEmails()
 * @method bool getAssignmentDeadlineEmails()
 * @method bool getSubmissionEvaluatedEmails()
 * @method bool getSolutionCommentsEmails()
 * @method bool getPointsChangedEmails()
 * @method setDarkTheme(bool $darkTheme)
 * @method setVimMode(bool $vimMode)
 * @method setOpenedSidebar(bool $opened)
 * @method setUseGravatar(bool $use)
 * @method setDefaultPage(?string $page)
 * @method setDefaultLanguage(string $language)
 * @method setNewAssignmentEmails(bool $flag)
 * @method setAssignmentDeadlineEmails(bool $flag)
 * @method setSubmissionEvaluatedEmails(bool $flag)
 * @method setSolutionCommentsEmails(bool $flag)
 * @method setPointsChangedEmails(bool $flag)
 */
class UserSettings implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  public function __construct(
    bool $darkTheme = true,
    bool $vimMode = false,
    string $defaultLanguage = "en",
    bool $openedSidebar = true,
    bool $useGravatar = true,
    string $defaultPage = null
  ) {
    $this->darkTheme = $darkTheme;
    $this->vimMode = $vimMode;
    $this->defaultLanguage = $defaultLanguage;
    $this->openedSidebar = $openedSidebar;
    $this->useGravatar = $useGravatar;
    $this->defaultPage = $defaultPage;

    $this->newAssignmentEmails = true;
    $this->assignmentDeadlineEmails = true;
    $this->submissionEvaluatedEmails = true;
    $this->solutionCommentsEmails = true;
    $this->pointsChangedEmails = true;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $darkTheme;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $vimMode;

  /**
   * @ORM\Column(type="string", length=32)
   */
  protected $defaultLanguage;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $openedSidebar;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $useGravatar;

  /**
   * @ORM\Column(type="string", nullable=true)
   * Default page identifier (set and interpreted by the UI only).
   */
  protected $defaultPage = null;


  /*******************
   * Emails settings *
   *******************/

  /**
   * @ORM\Column(type="boolean")
   */
  protected $newAssignmentEmails;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $assignmentDeadlineEmails;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $submissionEvaluatedEmails;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $solutionCommentsEmails;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $pointsChangedEmails;


  public function jsonSerialize() {
    return [
      "darkTheme" => $this->darkTheme,
      "vimMode" => $this->vimMode,
      "defaultLanguage" => $this->defaultLanguage,
      "openedSidebar" => $this->openedSidebar,
      "useGravatar" => $this->useGravatar,
      "defaultPage" => $this->defaultPage,
      "newAssignmentEmails" => $this->newAssignmentEmails,
      "assignmentDeadlineEmails" => $this->assignmentDeadlineEmails,
      "submissionEvaluatedEmails" => $this->submissionEvaluatedEmails,
      "solutionCommentsEmails" => $this->solutionCommentsEmails,
      "pointsChangedEmails" => $this->pointsChangedEmails
    ];
  }
}
