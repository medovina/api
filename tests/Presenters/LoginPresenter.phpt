<?php

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\ExternalLogin\CAS\LDAPLoginService;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Security\AccessToken;
use App\Security\Identity;
use App\Security\TokenScope;
use App\V1Module\Presenters\LoginPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";


/**
 * @testCase
 */
class TestLoginPresenter extends Tester\TestCase
{
  private $userLogin = "user2@example.com";
  private $userPassword = "password2";

  /** @var LoginPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  /** @var \App\Model\Repository\Logins */
  private $logins;

  public function __construct($container)
  {
    $this->container = $container;
    $this->em = PresenterTestHelper::getEntityManager($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, LoginPresenter::class);
  }

  protected function tearDown()
  {
    $this->user->logout(true);
    Mockery::close();
  }

  public function testLogin()
  {
    $request = new Request("V1:Login", "POST", ["action" => "default"], [
      "username" => $this->userLogin,
      "password" => $this->userPassword
    ]);

    /** @var JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::same($this->presenter->users->getByEmail($this->userLogin)->getId(), $result["payload"]["user"]["id"]);
    Assert::true($this->presenter->user->isLoggedIn());
  }

  public function testLoginIncorrect()
  {
    $request = new Request("V1:Login", "POST", ["action" => "default"], [
      "username" => $this->userLogin,
      "password" => $this->userPassword . "42"
    ]);

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, WrongCredentialsException::class);

    Assert::false($this->presenter->user->isLoggedIn());
  }

  public function testLoginExternal()
  {
    $user = $this->presenter->users->getByEmail($this->userLogin);
    $mockAuthenticator = Mockery::mock(ExternalServiceAuthenticator::class);

    $service = Mockery::mock(LDAPLoginService::class);
    $mockAuthenticator->shouldReceive("findService")
      ->with("foo", null)
      ->andReturn($service);

    $credentials = [ "username" => $this->userLogin, "password" => $this->userPassword ];

    $mockAuthenticator->shouldReceive("authenticate")
      ->with($service, $credentials)
      ->andReturn($user);

    $this->presenter->externalServiceAuthenticator = $mockAuthenticator;

    $request = new Request("V1:Login", "POST", ["action" => "external", "serviceId" => "foo"], $credentials);

    /** @var JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::equal($user->getId(), $result["payload"]["user"]["id"]);
    Assert::true($this->presenter->user->isLoggedIn());
  }

  public function testTakeover()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->presenter->users->getByEmail($this->userLogin);

    $request = new Nette\Application\Request('V1:Login',
      'POST',
      ['action' => 'takeOver', 'userId' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::same(200, $result["code"]);

    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::equal($user->getId(), $result["payload"]["user"]["id"]);
  }

  public function testTakeoverIncorrect()
  {
    $user = $this->presenter->users->getByEmail($this->userLogin);

    $request = new Nette\Application\Request('V1:Login',
      'POST',
      ['action' => 'takeOver', 'userId' => $user->getId()]
    );

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, ForbiddenRequestException::class);
  }

  public function testRefresh()
  {
    $user = $this->presenter->users->getByEmail($this->userLogin);
    $time = time();
    $token = new AccessToken((object) [
      "scopes" => [TokenScope::REFRESH, "hello", "world"],
      "sub" => $user->getId(),
      "exp" => $time + 1200,
      "ref" => $time + 2400,
      "iat" => $time - 1200
    ]);

    $this->presenter->user->login(new Identity($user, $token));

    $request = new Request("V1:Login", "POST", ["action" => "refresh"], []);

    /** @var JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::same($user->getId(), $result["payload"]["user"]["id"]);
    Assert::true($this->presenter->user->isLoggedIn());

    $newToken = $this->presenter->accessManager->decodeToken($result["payload"]["accessToken"]);
    Assert::true($newToken->isInScope(TokenScope::REFRESH));
    Assert::true($newToken->isInScope("hello"));
    Assert::true($newToken->isInScope("world"));
  }

  public function testRefreshWrongScope()
  {
    $user = $this->presenter->users->getByEmail($this->userLogin);
    $time = time();
    $token = new AccessToken((object) [
      "scopes" => [],
      "sub" => $user->getId(),
      "exp" => $time + 1200,
      "ref" => $time + 2400,
      "iat" => $time - 1200
    ]);

    $this->presenter->user->login(new Identity($user, $token));
    $request = new Request("V1:Login", "POST", ["action" => "refresh"], []);

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, ForbiddenRequestException::class);
  }

  public function testIssueToken()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $request = new Request("V1:Login", "POST", ["action" => "issueRestrictedToken"], ["scopes" => [TokenScope::REFRESH, "read-all"], "expiration" => "3000"]);
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $payload = $response->getPayload()["payload"];
    $token = $this->presenter->accessManager->decodeToken($payload["accessToken"]);
    Assert::true($token->isInScope(TokenScope::REFRESH));
    Assert::true($token->isInScope("read-all"));
  }
}

(new TestLoginPresenter($container))->run();
