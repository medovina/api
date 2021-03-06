<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Environment;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\PipelinesCache;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\Validation\ExerciseConfigValidator;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Entity\ExerciseTest;
use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\User;
use Nette\DI\Container;
use Tester\Assert;


/**
 * @testCase
 */
class TestExerciseConfigValidator extends Tester\TestCase
{
  /**
   * @var Mockery\Mock | PipelinesCache
   */
  private $mockPipelinesCache;

  const EMPTY_PIPELINE_CONFIG = [
    "boxes" => [],
    "variables" => []
  ];

  /**
   * @var ExerciseConfigValidator
   */
  private $validator;

  /**
   * @var Loader
   */
  private $loader;

  public function __construct() {
    $this->mockPipelinesCache = Mockery::mock(PipelinesCache::class);

    $this->loader = new Loader(new BoxService());
    $helper = new Helper($this->loader, $this->mockPipelinesCache);
    $this->validator = new ExerciseConfigValidator($this->loader, $helper);
  }


  public function testMissingEnvironment() {
    $exerciseConfig = new ExerciseConfig();
    $exercise = $this->createExercise();
    $this->addTwoEnvironmentsToExercise($exercise);
    $this->addTwoTestsToExercise($exercise);

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class, "Exercise configuration error - The number of entries in environment-specific configuration differs from the number of allowed environments");
  }

  public function testDifferentEnvironments() {
    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addEnvironment("envB");

    $exercise = $this->createExercise();
    $user = $exercise->getAuthor();
    $this->addTwoTestsToExercise($exercise);

    $envC = new RuntimeEnvironment("envC", "Env C", "C", ".c", "", "");
    $envD = new RuntimeEnvironment("envD", "Env D", "D", ".d", "", "");
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envC, "",  $user, null));
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envD, "",  $user, null));

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class, "Exercise configuration error - Environment envA not found in environment-specific configuration");
  }

  public function testDifferentNumberOfEnvironments() {
    $exerciseConfig = new ExerciseConfig();
    $exercise = $this->createExercise();
    $this->addTwoEnvironmentsToExercise($exercise);
    $exerciseConfig->addEnvironment("envA");

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class, "Exercise configuration error - The number of entries in environment-specific configuration differs from the number of allowed environments");
  }

  public function testMissingEnvironmentPipeline() {
    $notExisting = new PipelineVars();
    $notExisting->setId("not existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($notExisting);

    $test = new Test();
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("1", $test);
    $exerciseConfig->addTest("2", $test);

    $exercise = $this->createExercise();
    $this->addTwoTestsToExercise($exercise);
    $this->addSingleEnvironmentToExercise($exercise);

    // setup mock pipelines
    $this->mockPipelinesCache->shouldReceive("getPipelineConfig")->withArgs(["not existing pipeline"])
      ->andThrow(NotFoundException::class);
    $this->mockPipelinesCache->shouldReceive("getPipelineConfig")->withArgs(["existing pipeline"])
      ->andReturn($this->loader->loadPipeline(self::EMPTY_PIPELINE_CONFIG));

    // missing in environments
    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class, "Exercise configuration error - Pipeline 'not existing pipeline' not found");
  }

  public function testDifferentNumberOfTests() {
    $existing = new PipelineVars();
    $existing->setId("existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($existing);

    $test = new Test();
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("1", $test);

    $exercise = $this->createExercise();
    $this->addTwoTestsToExercise($exercise);
    $this->addSingleEnvironmentToExercise($exercise);

    // setup mock pipelines
    $this->mockPipelinesCache->shouldReceive("getPipelineConfig")->withArgs(["existing pipeline"])
      ->andReturn($this->loader->loadPipeline(self::EMPTY_PIPELINE_CONFIG));

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class, "Exercise configuration error - Number of tests in configuration do not correspond to the ones in exercise");
  }

  public function testDifferentTestIds() {
    $existing = new PipelineVars();
    $existing->setId("existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($existing);

    $test = new Test();
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("3", $test);
    $exerciseConfig->addTest("4", $test);

    $exercise = $this->createExercise();
    $this->addTwoTestsToExercise($exercise);
    $this->addSingleEnvironmentToExercise($exercise);

    // setup mock pipelines
    $this->mockPipelinesCache->shouldReceive("getPipelineConfig")->withArgs(["existing pipeline"])
      ->andReturn($this->loader->loadPipeline(self::EMPTY_PIPELINE_CONFIG));

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class, "Exercise configuration error - Test with id '3' not found in exercise tests");
  }

  public function testEmpty() {
    $exerciseConfig = new ExerciseConfig();
    $user = $this->getDummyUser();
    $exercise = Exercise::create($user, new Group("ext", new Instance()));

    Assert::noError(
      function () use ($exerciseConfig, $exercise) {
        $this->validator->validate($exerciseConfig, $exercise);
      }
    );
  }

  public function testCorrect() {
    $existing = new PipelineVars();
    $existing->setId("existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($existing);

    $test = new Test();
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("1", $test);
    $exerciseConfig->addTest("2", $test);

    $exercise = $this->createExercise();
    $this->addTwoTestsToExercise($exercise);
    $this->addSingleEnvironmentToExercise($exercise);

    // setup mock pipelines
    $this->mockPipelinesCache->shouldReceive("getPipelineConfig")->withArgs(["existing pipeline"])
      ->andReturn($this->loader->loadPipeline(self::EMPTY_PIPELINE_CONFIG));

    Assert::noError(
      function () use ($exerciseConfig, $exercise) {
        $this->validator->validate($exerciseConfig, $exercise);
      }
    );
  }


  /**
   * @return Exercise
   */
  private function createExercise(): Exercise {
    $user = $this->getDummyUser();
    $exercise = Exercise::create($user, new Group("ext", new Instance()));
    return $exercise;
  }

  /**
   * @param Exercise $exercise
   * @return Exercise
   */
  private function addTwoEnvironmentsToExercise(Exercise $exercise): Exercise
  {
    $user = $exercise->getAuthor();
    $envA = new RuntimeEnvironment("envA", "Env A", "A", ".a", "", "");
    $envB = new RuntimeEnvironment("envB", "Env B", "B", ".b", "", "");
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envA, "[]", $user, null));
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envB, "[]", $user, null));
    return $exercise;
  }

  /**
   * @param Exercise $exercise
   * @return Exercise
   */
  private function addTwoTestsToExercise(Exercise $exercise): Exercise {
    $user = $exercise->getAuthor();
    $testA = new ExerciseTest("Test A", "descA", $user);
    $testB = new ExerciseTest("Test B", "descB", $user);
    $testA->setId(1);
    $testB->setId(2);
    $exercise->addExerciseTest($testA);
    $exercise->addExerciseTest($testB);
    return $exercise;
  }

  /**
   * @param Exercise $exercise
   * @return Exercise
   */
  private function addSingleEnvironmentToExercise(Exercise $exercise): Exercise
  {
    $user = $exercise->getAuthor();
    $envA = new RuntimeEnvironment("envA", "Env A", "A", ".a", "", "");
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envA, "[]", $user, null));
    return $exercise;
  }

  /**
   * @return User
   */
  private function getDummyUser(): User
  {
    $user = new User("", "", "", "", "", "", new Instance());
    return $user;
  }
}

# Testing methods run
$testCase = new TestExerciseConfigValidator();
$testCase->run();
