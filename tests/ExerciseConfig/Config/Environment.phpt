<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Environment;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\VariableFactory;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestEnvironment extends Tester\TestCase
{
  static $config = [
    "pipelines" => [
      "hello" => [
        "variables" => [
          "hello" => [ "type" => "string", "value" => "world" ],
          "world" => [ "type" => "string", "value" => "hello" ]
        ]
      ],
      "world" => [
        "variables" => []
      ]
    ],

  ];

  static $pipelines = [
    "pipelines" => [
      "pipelineA" => [
        "variables" => []
      ],
      "pipelineB" => [
        "variables" => []
      ]
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new VariableFactory());
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadEnvironment(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testParsingPipelines() {
    $env = $this->loader->loadEnvironment(self::$pipelines);
    Assert::count(2, $env->getPipelines());

    Assert::type(Pipeline::class, $env->getPipeline("pipelineA"));
    Assert::type(Pipeline::class, $env->getPipeline("pipelineB"));
  }

  public function testPipelinesOperations() {
    $environment = new Environment();
    $pipeline = new Pipeline;

    $environment->addPipeline("pipelineA", $pipeline);
    Assert::type(Pipeline::class, $environment->getPipeline("pipelineA"));

    $environment->removePipeline("non-existant");
    Assert::count(1, $environment->getPipelines());

    $environment->removePipeline("pipelineA");
    Assert::count(0, $environment->getPipelines());
  }

  public function testCorrect() {
    $env = $this->loader->loadEnvironment(self::$config);
    Assert::count(2, $env->getPipelines());
    Assert::equal("hello", $env->getPipeline("hello")->getVariable("world")->getValue());
    Assert::equal("world", $env->getPipeline("hello")->getVariable("hello")->getValue());
    Assert::equal(self::$config, $env->toArray());
  }

}

# Testing methods run
$testCase = new TestEnvironment;
$testCase->run();
