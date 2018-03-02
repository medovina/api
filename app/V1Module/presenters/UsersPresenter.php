<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\WrongCredentialsException;
use App\Model\Entity\Group;
use App\Model\Entity\Login;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use App\Exceptions\BadRequestException;
use App\Helpers\EmailVerificationHelper;
use App\Model\View\GroupViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\AccessToken;
use App\Security\ACL\IUserPermissions;

/**
 * User management endpoints
 */
class UsersPresenter extends BasePresenter {

  /**
   * @var Logins
   * @inject
   */
  public $logins;

  /**
   * @var EmailVerificationHelper
   * @inject
   */
  public $emailVerificationHelper;

  /**
   * @var GroupViewFactory
   * @inject
   */
  public $groupViewFactory;

  /**
   * @var UserViewFactory
   * @inject
   */
  public $userViewFactory;

  /**
   * @var IUserPermissions
   * @inject
   */
  public $userAcl;

  /**
   * Get a list of all users
   * @GET
   * @throws ForbiddenRequestException
   */
  public function actionDefault() {
    if (!$this->userAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $users = $this->users->findAll();
    $users = array_map(function (User $user) {
      return $this->userViewFactory->getUser($user);
    }, $users);
    $this->sendSuccessResponse($users);
  }

  /**
   * Get details of a user account
   * @GET
   * @param string $id Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $user = $this->users->findOrThrow($id);
    if (!$this->userAcl->canViewPublicData($user)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($this->userViewFactory->getUser($user));
  }

  /**
   * Delete a user account
   * @DELETE
   * @param string $id Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionDelete(string $id) {
    $user = $this->users->findOrThrow($id);
    if (!$this->userAcl->canDelete($user)) {
      throw new ForbiddenRequestException();
    }
    $this->users->remove($user);
    $this->sendSuccessResponse("OK");
  }

  /**
   * Update the profile associated with a user account
   * @POST
   * @param string $id Identifier of the user
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @Param(type="post", name="firstName", required=false, validation="string:2..", description="First name")
   * @Param(type="post", name="lastName", required=false, validation="string:2..", description="Last name")
   * @Param(type="post", name="degreesBeforeName", description="Degrees before name")
   * @Param(type="post", name="degreesAfterName", description="Degrees after name")
   * @Param(type="post", name="email", validation="email", description="New email address", required=false)
   * @Param(type="post", name="oldPassword", required=false, validation="string:1..", description="Old password of current user")
   * @Param(type="post", name="password", required=false, validation="string:1..", description="New password of current user")
   * @Param(type="post", name="passwordConfirm", required=false, validation="string:1..", description="Confirmation of new password of current user")
   * @throws WrongCredentialsException
   */
  public function actionUpdateProfile(string $id) {
    $req = $this->getRequest();
    $degreesBeforeName = $req->getPost("degreesBeforeName");
    $degreesAfterName = $req->getPost("degreesAfterName");

    // fill user with provided data
    $user = $this->users->findOrThrow($id);
    $login = $this->logins->findCurrent();

    if (!$this->userAcl->canUpdateProfile($user)) {
      throw new ForbiddenRequestException();
    }

    // change details in separate methods
    $this->changeUserEmail($user, $login, $req->getPost("email"));
    $this->changeFirstAndLastName($user, $req->getPost("firstName"), $req->getPost("lastName"));
    $this->changeUserPassword($login, $req->getPost("oldPassword"),
      $req->getPost("password"), $req->getPost("passwordConfirm"));

    $user->setDegreesBeforeName($degreesBeforeName);
    $user->setDegreesAfterName($degreesAfterName);

    // make changes permanent
    $this->users->flush();
    $this->logins->flush();

    $this->sendSuccessResponse($this->userViewFactory->getUser($user));
  }

  /**
   * Change email if any given of the provided user.
   * @param User $user
   * @param Login|null $login
   * @param null|string $email
   * @throws BadRequestException
   * @throws InvalidArgumentException
   */
  private function changeUserEmail(User $user, ?Login $login, ?string $email) {
    $email = trim($email);
    if ($email === null || strlen($email) === 0) {
      return;
    }

    // check if there is not another user using provided email
    $userEmail = $this->users->getByEmail($email);
    if ($userEmail !== null && $userEmail->getId() !== $user->getId()) {
      throw new BadRequestException("This email address is already taken.");
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      throw new InvalidArgumentException("Provided email is not in correct format");
    }

    $oldEmail = $user->getEmail();
    if (strtolower($oldEmail) !== strtolower($email)) {
      // old and new email are not same, we have to changed and verify it
      $user->setEmail($email);

      // do not forget to change local login (if any)
      if ($login) {
        $login->setUsername($email);
      }

      // email has to be re-verified
      $user->setVerified(false);
      $this->emailVerificationHelper->process($user);
    }
  }

  /**
   * Change firstname and second name and check if user can change them.
   * @param User $user
   * @param null|string $firstname
   * @param null|string $lastname
   * @throws ForbiddenRequestException
   */
  public function changeFirstAndLastName(User $user, ?string $firstname, ?string $lastname) {
    if (($firstname !== null || $lastname !== null) &&
        !$this->userAcl->canUpdatePersonalData($user)) {
      throw new ForbiddenRequestException("You cannot update personal data");
    }

    if ($firstname) {
      $user->setFirstName($firstname);
    }

    if ($lastname) {
      $user->setLastName($lastname);
    }
  }

  /**
   * Change password of user if provided.
   * @param Login|null $login
   * @param null|string $oldPassword
   * @param null|string $password
   * @param null|string $passwordConfirm
   * @throws InvalidArgumentException
   * @throws WrongCredentialsException
   */
  private function changeUserPassword(?Login $login, ?string $oldPassword,
      ?string $password, ?string $passwordConfirm) {

    if (!$login || (!$oldPassword && !$password && !$passwordConfirm)) {
      // password was not provided, or user is not logged as local one
      return;
    }

    if (!$password || !$passwordConfirm) {
      // old password was provided but the new ones not, illegal state
      throw new InvalidArgumentException("New password was not provided");
    }

    // passwords need to be handled differently
    if ($login->passwordsMatch($oldPassword)) {
      // old password was provided, just check it against the one from db
      if ($password !== $passwordConfirm) {
        throw new WrongCredentialsException("Provided passwords do not match");
      }

      $login->changePassword($password);
    } else {
      throw new WrongCredentialsException("Your current password does not match");
    }
  }

  /**
   * Update the profile settings
   * @POST
   * @param string $id Identifier of the user
   * @Param(type="post", name="darkTheme", validation="bool", description="Flag if dark theme is used", required=false)
   * @Param(type="post", name="vimMode", validation="bool", description="Flag if vim keybinding is used", required=false)
   * @Param(type="post", name="openedSidebar", validation="bool", description="Flag if the sidebar of the web-app should be opened by default.", required=false)
   * @Param(type="post", name="defaultLanguage", validation="string", description="Default language of UI", required=false)
   * @Param(type="post", name="newAssignmentEmails", validation="bool", description="Flag if email should be sent to user when new assignment was created", required=false)
   * @Param(type="post", name="assignmentDeadlineEmails", validation="bool", description="Flag if email should be sent to user if assignment deadline is nearby", required=false)
   * @Param(type="post", name="submissionEvaluatedEmails", validation="bool", description="Flag if email should be sent to user when resubmission was evaluated", required=false)
   * @throws ForbiddenRequestException
   */
  public function actionUpdateSettings(string $id) {
    $req = $this->getRequest();
    $user = $this->users->findOrThrow($id);
    $settings = $user->getSettings();

    if (!$this->userAcl->canUpdateProfile($user)) {
      throw new ForbiddenRequestException();
    }

    $darkTheme = $req->getPost("darkTheme") !== null
      ? filter_var($req->getPost("darkTheme"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getDarkTheme();
    $vimMode = $req->getPost("vimMode") !== null
      ? filter_var($req->getPost("vimMode"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getVimMode();
    $openedSidebar = $req->getPost("openedSidebar") !== null
      ? filter_var($req->getPost("openedSidebar"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getOpenedSidebar();
    $defaultLanguage = $req->getPost("defaultLanguage") !== null ? $req->getPost("defaultLanguage") : $settings->getDefaultLanguage();
    $newAssignmentEmails = $req->getPost("newAssignmentEmails") !== null
      ? filter_var($req->getPost("newAssignmentEmails"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getNewAssignmentEmails();
    $assignmentDeadlineEmails = $req->getPost("assignmentDeadlineEmails") !== null
      ? filter_var($req->getPost("assignmentDeadlineEmails"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getAssignmentDeadlineEmails();
    $submissionEvaluatedEmails = $req->getPost("submissionEvaluatedEmails") !== null
      ? filter_var($req->getPost("submissionEvaluatedEmails"), FILTER_VALIDATE_BOOLEAN)
      : $settings->getSubmissionEvaluatedEmails();

    $settings->setDarkTheme($darkTheme);
    $settings->setVimMode($vimMode);
    $settings->setOpenedSidebar($openedSidebar);
    $settings->setDefaultLanguage($defaultLanguage);
    $settings->setNewAssignmentEmails($newAssignmentEmails);
    $settings->setAssignmentDeadlineEmails($assignmentDeadlineEmails);
    $settings->setSubmissionEvaluatedEmails($submissionEvaluatedEmails);

    $this->users->persist($user);
    $this->sendSuccessResponse($this->userViewFactory->getUser($user));
  }

  /**
   * If user is registered externally, add local account as another login method.
   * Created password is empty and has to be changed in order to use it.
   * @POST
   * @param string $id
   * @throws ForbiddenRequestException
   * @throws BadRequestException
   * @throws InvalidArgumentException
   */
  public function actionCreateLocalAccount(string $id) {
    $user = $this->users->findOrThrow($id);
    if (!$this->userAcl->canCreateLocalAccount($user)) {
      throw new ForbiddenRequestException();
    }

    if ($user->hasLocalAccounts()) {
      throw new BadRequestException("User is already registered locally");
    }

    Login::createLogin($user, $user->getEmail(), "");
    $this->users->flush();
    $this->sendSuccessResponse($this->userViewFactory->getUser($user));
  }

  /**
   * Get a list of non-archived groups for a user
   * @GET
   * @param string $id Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionGroups(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canViewGroups($user)) {
      throw new ForbiddenRequestException();
    }

    $asStudent = $user->getGroupsAsStudent()->filter(function (Group $group) {
      return !$group->isArchived();
    });

    $asSupervisor = $user->getGroupsAsSupervisor()->filter(function (Group $group) {
      return !$group->isArchived();
    });

    $this->sendSuccessResponse([
      "supervisor" => $this->groupViewFactory->getGroups($asSupervisor->getValues()),
      "student" => $this->groupViewFactory->getGroups($asStudent->getValues()),
      "stats" => $user->getGroupsAsStudent()->map(
        function (Group $group) use ($user) {
          return $this->groupViewFactory->getStudentsStats($group, $user);
        }
      )->getValues(),
    ]);
  }

  /**
   * Get a list of all groups for a user
   * @GET
   * @param string $id Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionAllGroups(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canViewGroups($user)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse([
      "supervisor" => $this->groupViewFactory->getGroups($user->getGroupsAsSupervisor()->getValues(), false),
      "student" => $this->groupViewFactory->getGroups($user->getGroupsAsStudent()->getValues(), false)
    ]);
  }

  /**
   * Get a list of instances where a user is registered
   * @GET
   * @param string $id Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionInstances(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canViewInstances($user)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse([
      $user->getInstance() // @todo change when the user can be member of multiple instances
    ]);
  }

  /**
   * Get a list of exercises authored by a user
   * @GET
   * @param string $id Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionExercises(string $id) {
    $user = $this->users->findOrThrow($id);

    if (!$this->userAcl->canViewExercises($user)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($user->getExercises()->getValues());
  }

}
