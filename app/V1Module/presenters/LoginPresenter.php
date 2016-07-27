<?php

namespace App\V1Module\Presenters;

use App\Exception\WrongCredentialsException;
use App\Model\Repository\Logins;
use App\Authentication\AccessManager;

class LoginPresenter extends BasePresenter {

  /** @inject @var Logins */
  public $logins;

  /** @inject @var AccessManager */
  public $accessManager;

  /**
   * @GET
   */
  public function actionDefault($username, $password) {
    $user = $this->logins->getUser($username, $password);
    if (!$user) {
      throw new WrongCredentialsException;
    }

    $this->sendSuccessResponse([
      'accessToken' => $this->accessManager->issueToken($user),
      'user' => $user
    ]);
  }

}
