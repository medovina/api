<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Limits;
use App\Helpers\JobConfig\UndefinedLimits;
use App\Exceptions\JobConfigLoadingException;

class TestSandboxConfig extends Tester\TestCase
{
  static $cfg = [
    "name" => "sandboxName",
    "limits" => [
      [ "hw-group-id" => "idA" ],
      [ "hw-group-id" => "idB" ]
    ]
  ];
  static $optional = [
    "name" => "optional",
    "stdin" => "optStdin",
    "stdout" => "optStdout",
    "stderr" => "optStderr",
    "limits" => [
      [ "hw-group-id" => "optionalGroup"]
    ]
  ];

  public function testMissingSandboxName() {
    Assert::exception(function () {
      $data = self::$cfg;
      unset($data["name"]);
      new SandboxConfig($data);
    }, JobConfigLoadingException::class);
  }

  public function testMissingLimits() {
    Assert::exception(function () {
      $data = self::$cfg;
      unset($data["limits"]);
      new SandboxConfig($data);
    }, JobConfigLoadingException::class);
  }

  public function testLimitsIsNotArray() {
    Assert::exception(function () {
      $data = self::$cfg;
      unset($data["limits"]);
      $data["limits"] = "hello";
      new SandboxConfig($data);
    }, JobConfigLoadingException::class);
  }

  public function testParsing() {
    $sandbox = new SandboxConfig(self::$cfg);
    Assert::equal("sandboxName", $sandbox->getName());
    Assert::equal(NULL, $sandbox->getStdin());
    Assert::equal(NULL, $sandbox->getStdout());
    Assert::equal(NULL, $sandbox->getStderr());
    Assert::equal(2, count($sandbox->getLimitsArray()));
    Assert::true($sandbox->hasLimits("idA"));
    Assert::true($sandbox->hasLimits("idB"));
    Assert::isEqual(self::$cfg, $sandbox->toArray());
  }

  public function testOptional() {
    $sandbox = new SandboxConfig(self::$optional);
    Assert::equal("optional", $sandbox->getName());
    Assert::equal("optStdin", $sandbox->getStdin());
    Assert::equal("optStdout", $sandbox->getStdout());
    Assert::equal("optStderr", $sandbox->getStderr());
    Assert::equal(1, count($sandbox->getLimitsArray()));
    Assert::true($sandbox->hasLimits("optionalGroup"));
    Assert::isEqual(self::$optional, $sandbox->toArray());
  }

  public function testHasLimits() {
    $sandbox = new SandboxConfig(self::$cfg);
    Assert::true($sandbox->hasLimits("idA"));
    Assert::true($sandbox->hasLimits("idB"));
    Assert::false($sandbox->hasLimits("nonExistingGroup"));
  }

  public function testGetLimits() {
    $sandbox = new SandboxConfig(self::$cfg);
    Assert::true($sandbox->hasLimits("idA"));
    Assert::type(Limits::class, $sandbox->getLimits("idA"));
    Assert::equal("idA", $sandbox->getLimits("idA")->getId());

    Assert::true($sandbox->hasLimits("idB"));
    Assert::type(Limits::class, $sandbox->getLimits("idB"));
    Assert::equal("idB", $sandbox->getLimits("idB")->getId());

    Assert::exception(function () use ($sandbox) {
      $sandbox->getLimits("nonExistingGroup");
    }, JobConfigLoadingException::class);
  }

  public function testSetLimits() {
    $limits = new Limits([ "hw-group-id" => "newGroup" ]);
    $sandbox = new SandboxConfig(self::$cfg);
    $sandbox->setLimits($limits);

    Assert::type(Limits::class, $sandbox->getLimits("newGroup"));
    Assert::true($sandbox->hasLimits("newGroup"));
    Assert::equal("newGroup", $sandbox->getLimits("newGroup")->getId());
  }

  public function testReplaceLimits() {
    $limits = new Limits([ "hw-group-id" => "idA", "time" => "25.5" ]);
    $sandbox = new SandboxConfig(self::$cfg);

    Assert::true($sandbox->hasLimits("idA"));
    Assert::equal(0.0, $sandbox->getLimits("idA")->getTimeLimit());

    $sandbox->setLimits($limits);
    Assert::true($sandbox->hasLimits("idA"));
    Assert::equal(25.5, $sandbox->getLimits("idA")->getTimeLimit());
  }

  public function testRemoveLimits() {
    $sandbox = new SandboxConfig(self::$cfg);
    Assert::true($sandbox->hasLimits("idA"));

    $sandbox->removeLimits("idA");
    Assert::true($sandbox->hasLimits("idA"));
    Assert::type(UndefinedLimits::class, $sandbox->getLimits("idA"));
  }

}

# Testing methods run
$testCase = new TestSandboxConfig;
$testCase->run();
