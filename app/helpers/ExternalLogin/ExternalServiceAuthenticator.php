<?php

namespace App\Helpers\ExternalLogin;

use App\Exceptions\BadRequestException;
use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidStateException;
use App\Exceptions\WrongCredentialsException;
use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Logins;
use App\Model\Repository\Users;
use App\V1Module\Presenters\RegistrationPresenter;


/**
 * Mapper of service identification to object instance
 */
class ExternalServiceAuthenticator {

  /**
   * External authentication services
   * @var IExternalLoginService[]
   */
  private $services;

  /**
   * @var ExternalLogins
   */
  private $externalLogins;

  /**
   * @var Users
   */
  private $users;

  /**
   * @var Logins
   */
  private $logins;

  /**
   * Constructor with instantiation of all login services
   * @param ExternalLogins $externalLogins
   * @param Users $users
   * @param Logins $logins
   * @param array $services
   */
  public function __construct(ExternalLogins $externalLogins, Users $users, Logins $logins, ...$services) {
    $this->externalLogins = $externalLogins;
    $this->users = $users;
    $this->services = $services;
    $this->logins = $logins;
  }

  /**
   * Get external service depending on the ID
   * @param string $serviceId Identifier of wanted service
   * @param string|null $type Type of authentication process
   * @return IExternalLoginService Instance of login service with given ID
   * @throws BadRequestException when such service is not known
   */
  public function findService(string $serviceId, ?string $type = "default"): IExternalLoginService {
    foreach ($this->services as $service) {
      if ($service->getServiceId() === $serviceId && $service->getType() === $type) {
        return $service;
      }
    }

    throw new BadRequestException("Authentication service '$serviceId/$type' is not supported.");
  }

  /**
   * Authenticate a user against given external authentication service
   * @param IExternalLoginService $service
   * @param array $credentials
   * @return User
   * @throws BadRequestException
   * @throws WrongCredentialsException
   */
  public function authenticate(IExternalLoginService $service, $credentials) {
    $user = null;
    $userData = $service->getUser($credentials, true); // true = only ID
    try {
      $user = $this->findUser($service, $userData);
    } catch (WrongCredentialsException $e) {
      // second try - is there a user with a verified email corresponding to this one?
      if ($userData !== null) {
        $user = $this->tryConnect($service, $userData);
      }

      if ($user === null) {
        throw $e;
      }
    }

    return $user;
  }

  /**
   * Register and authenticate user against given external authentication service.
   * @param IExternalLoginService $service
   * @param Instance $instance
   * @param array $credentials
   * @return User
   * @throws BadRequestException
   * @throws WrongCredentialsException
   */
  public function register(IExternalLoginService $service, Instance $instance, $credentials): User {
    $userData = $service->getUser($credentials); // throws if the user cannot be logged in
    $user = $this->externalLogins->getUser($service->getServiceId(), $userData->getId());

    if ($user !== null) {
      throw new WrongCredentialsException(
        "User is already registered using '{$service->getServiceId()}'.",
        FrontendErrorMappings::E400_106__WRONG_CREDENTIALS_EXTERNAL_USER_REGISTERED,
        [ "service" => $service->getServiceId() ]
      );
    }

    // try to connect new user to already existing ones
    $user = $this->tryConnect($service, $userData);
    if ($user === null) {
      // user is not registered locally, create brand new one
      $user = $userData->createEntity($instance);
      $this->users->persist($user);
      // connect the account to the login method
      $this->externalLogins->connect($service, $user, $userData->getId());
    }

    return $user;
  }

  /**
   * Try to find a user account based on the data collected from
   * an external login service.
   * @param IExternalLoginService $service
   * @param UserData|null $userData
   * @return User
   * @throws WrongCredentialsException
   */
  private function findUser(IExternalLoginService $service, ?UserData $userData): User {
    if ($userData === null) {
      throw new WrongCredentialsException(
        "External authentication failed.",
        FrontendErrorMappings::E400_104__WRONG_CREDENTIALS_EXTERNAL_FAILED
      );
    }

    $user = $this->externalLogins->getUser($service->getServiceId(), $userData->getId());
    if ($user === null) {
      throw new WrongCredentialsException(
        "User authenticated through '{$service->getServiceId()}' has no corresponding account in ReCodEx. Please register to ReCodEx first.",
        FrontendErrorMappings::E400_105__WRONG_CREDENTIALS_EXTERNAL_USER_NOT_FOUND,
        [ "service" => $service->getServiceId() ]
      );
    }

    return $user;
  }


  /**
   * Try connecting given external user to local ReCodEx user account.
   * @param IExternalLoginService $service
   * @param UserData $userData
   * @return User|null
   * @throws BadRequestException
   */
  private function tryConnect(IExternalLoginService $service, UserData $userData): ?User {
    $unconnectedUsers = [];
    foreach ($userData->getEmails() as $email) {
      if (empty($email)) {
        continue;
      }

      $user = $this->users->getByEmail($email);
      if ($user) {
        $unconnectedUsers[] = $user;
      }
    }

    if (count($unconnectedUsers) === 0) {
      // no recodex users are suitable for connecting to external service account
      return null;
    } else if (count($unconnectedUsers) > 1) {
      // multiple recodex accounts were found for emails in external service
      throw new BadRequestException(
        sprintf("User '%s' has multiple specified emails (%s) which are also registered locally in ReCodEx",
          $userData->getId(), join(", ", $userData->getEmails())),
        FrontendErrorMappings::E400_001__BAD_REQUEST_EXT_MULTIPLE_USERS_FOUND,
        [ "user" => $userData->getId(), "emails" => $userData->getEmails() ]
      );
    }

    // there was only one suitable user, try to connect it
    $user = current($unconnectedUsers);
    $this->externalLogins->connect($service, $user, $userData->getId());
    // and also clear local account password just to be sure
    $this->logins->clearUserPassword($user);
    return $user;
  }

}
