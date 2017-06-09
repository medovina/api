<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\ExerciseConfig\Test;
use App\V1Module\Presenters\ExercisesConfigPresenter;
use Tester\Assert;

class TestExercisesConfigPresenter extends Tester\TestCase
{
  /** @var ExercisesConfigPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  /** @var App\Model\Repository\Exercises */
  protected $exercises;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->runtimeEnvironments = $container->getByType(\App\Model\Repository\RuntimeEnvironments::class);
    $this->exercises = $container->getByType(App\Model\Repository\Exercises::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, ExercisesConfigPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetConfiguration()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseConfig = $this->presenter->exerciseConfigLoader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'GET',
      [
        'action' => 'getConfiguration',
        'id' => $exercise->getId()
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $payload = $result['payload'];

    // check all environments
    foreach ($payload as $environment) {
      Assert::contains($environment['name'], [ "default", "java8", "cpp11" ]);
      Assert::count(count($exerciseConfig->getTests()), $environment['tests']);

      foreach ($environment['tests'] as $test) {
        $testId = $test['name'];
        Assert::notEqual($exerciseConfig->getTest($testId), null);
      }
    }
  }

  public function testSetConfiguration()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());

    // prepare config array
    $config = [
      [
        "name" => "default",
        "tests" => [
          ["name" => "testA", "pipelines" => ["defaultTestA"], "variables" => ["defVarA" => "defValA"]],
          ["name" => "testB", "pipelines" => ["defaultTestB"], "variables" => ["defVarB" => "defValB"]]
        ]
      ],
      [
        "name" => "environmentA",
        "tests" => [
          ["name" => "testA", "pipelines" => ["ATestA"], "variables" => ["AVarA" => "AValA"]],
          ["name" => "testB", "pipelines" => ["ATestB"], "variables" => ["AVarB" => "AValB"]]
        ]
      ],
      [
        "name" => "environmentB",
        "tests" => [
          ["name" => "testA", "pipelines" => ["BTestA"], "variables" => ["BVarA" => "BValA"]],
          ["name" => "testB", "pipelines" => ["BTestB"], "variables" => ["BVarB" => "BValB"]]
        ]
      ]
    ];

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      [
        'action' => 'setConfiguration',
        'id' => $exercise->getId()
      ],
      ['config' => $config]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);

    $exerciseConfig = $this->presenter->exerciseConfigLoader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    Assert::count(2, $exerciseConfig->getTests());
    Assert::type(Test::class, $exerciseConfig->getTest('testA'));
    Assert::type(Test::class, $exerciseConfig->getTest('testB'));
    Assert::equal(["defaultTestA"], $exerciseConfig->getTest('testA')->getPipelines());
    Assert::equal(["defaultTestB"], $exerciseConfig->getTest('testB')->getPipelines());
    Assert::equal("defValA", $exerciseConfig->getTest('testA')->getVariableValue('defVarA'));
    Assert::equal("defValB", $exerciseConfig->getTest('testB')->getVariableValue('defVarB'));
  }

  public function testGetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'GET',
      [
          'action' => 'getLimits',
          'id' => $exercise->getId(),
          'runtimeEnvironmentId' => $exerciseLimits->getRuntimeEnvironment()->getId(),
          'hwGroupId' => $exerciseLimits->getHardwareGroup()->getId()
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(1, $result['payload']);

    $structured = $exerciseLimits->getParsedLimits();
    Assert::equal($structured, $result['payload']);
  }

  public function testSetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();

    // prepare limits arrays
    $limits = [
      'box-id-1' => ['wall-time' => 1.0],
      'box-id-2' => ['wall-time' => 2.0]
    ];

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      [
          'action' => 'setLimits',
          'id' => $exercise->getId(),
          'runtimeEnvironmentId' => $exerciseLimits->getRuntimeEnvironment()->getId(),
          'hwGroupId' => $exerciseLimits->getHardwareGroup()->getId()
      ],
      ['limits' => $limits]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $updatedLimits = $result['payload'];
    Assert::same($updatedLimits, $limits);
  }
}

$testCase = new TestExercisesConfigPresenter();
$testCase->run();
