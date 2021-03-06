<?php

namespace App\V1Module\Router;

use Nette\Http\IRequest;
use Nette\Http\Url;
use Nette\Application\IRouter;
use Nette\Application\Request;
use Nette\Application\Routers\Route;

/**
 * Base class of all module routes which construct URLs. And also does all
 * checkings of HTTP request method against the one given during construction.
 */
class MethodRoute implements IRouter {

  /** @var string */
  private $method;

  /** @var Route */
  private $route;

  /**
   * @param string        $method     The HTTP method which is accepted by this route
   * @param string        $mask       Mask for the Nette\Application\Routers\Route
   * @param string|array  $metadata   Metadata for the Nette\Application\Routers\Route
   * @param int           $flags      Flags for the Nette\Application\Routers\Route
   */
  public function __construct(string $method, string $mask, $metadata = [], int $flags = 0) {
    $this->method = $method;
    $this->route = new Route($mask, $metadata, $flags);
  }

  /**
   * Maps HTTP request to a Request object.
   * @return Request|null
   */
  public function match(IRequest $httpRequest) {
    if (!$httpRequest->isMethod($this->method)) {
      return null;
    }

    return $this->route->match($httpRequest);
  }

  /**
   * Constructs absolute URL from Request object.
   * @return string|null
   */
  public function constructUrl(Request $appRequest, Url $refUrl) {
    return $this->route->constructUrl($appRequest, $refUrl);
  }

}
