<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\ExercisesPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;

class TestExercisesPresenter extends Tester\TestCase
{
  private $adminLogin = "admin@admin.com";
  private $adminPassword = "admin";

  /** @var ExercisesPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, ExercisesPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testListAllExercises()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->presenter->exercises->findAll(), $result['payload']);
    Assert::equal(5, count($result['payload']));
  }

  public function testListSearchExercises()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default', 'search' => 'al']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(3, count($result['payload']));
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'detail', 'id' => $exercise->id]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::same($exercise, $result['payload']);
  }

  public function testUpdateDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $request = new Nette\Application\Request('V1:Exercises',
      'POST',
      ['action' => 'updateDetail', 'id' => $exercise->id],
      [
        'name' => 'new name',
        'difficulty' => 'super hard',
        'localizedAssignments' => [
          [
            'locale' => 'cs-CZ',
            'description' => 'new descr',
            'name' => 'SomeNeatyName'
          ]
        ]
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal('new name', $result['payload']->name);
    Assert::equal('super hard', $result['payload']->difficulty);

    $updatedLocalizedAssignments = $result['payload']->localizedAssignments;
    Assert::count(count($exercise->localizedAssignments) + 1, $updatedLocalizedAssignments);

    foreach ($exercise->localizedAssignments as $localized) {
      Assert::true($updatedLocalizedAssignments->contains($localized));
    }
    Assert::true($updatedLocalizedAssignments->exists(function ($key, $localized) {
      if ($localized->locale == "cs-CZ"
          && $localized->description == "new descr"
          && $localized->name == "SomeNeatyName") {
        return TRUE;
      }

      return FALSE;
    }));
  }

  public function testCreate()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Exercises', 'POST', ['action' => 'create']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->adminLogin, $result['payload']->getAuthor()->email);
  }

  public function testForkFrom()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $request = new Nette\Application\Request('V1:Exercises', 'POST', ['action' => 'forkFrom', 'id' => $exercise->id]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->adminLogin, $result['payload']->getAuthor()->email);
    Assert::notEqual($exercise->id, $result['payload']->id);
    Assert::equal(count($exercise->localizedAssignments), count($result['payload']->localizedAssignments));
    Assert::equal($exercise->difficulty, $result['payload']->difficulty);
  }

  public function testUpdateRuntimeConfigs()
  {
    // TODO
  }

}

$testCase = new TestExercisesPresenter();
$testCase->run();
