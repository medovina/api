<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

class InvalidAccessTokenException extends ApiException {

  /**
   * @param string $token   Access token from the HTTP request
   */
  public function __construct($token) {
    parent::__construct("Access token '$token' is not valid.", IResponse::S401_UNAUTHORIZED);
  }

  public function getAdditionalHttpHeaders() {
    return array_merge(
      parent::getAdditionalHttpHeaders(),
      [ "WWW-Authenticate" => 'Bearer realm="ReCodEx"' ]
    );
  }

}
