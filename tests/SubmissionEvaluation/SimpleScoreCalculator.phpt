<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\Evaluation\SimpleScoreCalculator;
use Doctrine\Common\Collections\ArrayCollection;

use App\Model\Entity\TestResult;

/**
 * @testCase
 */
class TestSimpleScoreCalculator extends Tester\TestCase
{
  private $scoreConfig = "testWeights:
  a: 300 # number between 1 and 1000
  b: 200 # sum of all numbers must be 1000
  c: 100
  d: 100
  e: 100
  f: 200";
  private $testNames = ["a", "b", "c", "d", "e", "f"];

  private function getCalc() { return new SimpleScoreCalculator(); }

  private function computeScore(array $scoreList) {
    return $this->getCalc()->computeScore($this->scoreConfig, $this->getCfg($scoreList));
  }

  private function getCfg(array $scoreList) {
    $scores = [];
    for ($i = 0; $i < count($scoreList); $i++) {
      $scores[$this->testNames[$i]] = $scoreList[$i];
    }

    return $scores;
  }

  public function testInvalidYamlScoreConfig() {
    $cfg = "\"asd";
    Assert::false($this->getCalc()->isScoreConfigValid($cfg));
  }

  public function testScoreConfigNonIntegerWeights() {
    $cfg = "testWeights:\n  a: a";
    Assert::false($this->getCalc()->isScoreConfigValid($cfg));
  }

  public function testScoreConfigDifferentWeightCount() {
    $calc = new SimpleScoreCalculator();
    $score = $calc->computeScore($this->scoreConfig, [ "a" => 0.5, "b" => 1 ]);
    Assert::equal(0.7, $score);
  }

  public function testScoreConfigWrongTestName() {
    $cfg = "testWeights:\n  b: 1";
    Assert::true($this->getCalc()->isScoreConfigValid($cfg));
  }

  public function testAllPassed() {
    Assert::equal(1.0, $this->computeScore([1, 1, 1, 1, 1, 1]));
  }

  public function testAllFailed() {
    Assert::equal(0.0, $this->computeScore([0, 0, 0, 0, 0, 0]));
  }

  public function testHalfPassed() {
    Assert::equal(0.6, $this->computeScore([1, 1, 1, 0, 0, 0]));
  }

  public function testEmptyWeights() {
    $calc = new SimpleScoreCalculator();
    $cfg = $this->getCfg([0]);
    $score = $calc->computeScore("testWeights: {  }\n", $cfg);
    Assert::equal(0.0, $score);
  }

  public function testScoreConfigValid() {
    Assert::true($this->getCalc()->isScoreConfigValid($this->scoreConfig));
  }

  public function testDefaultConfig() {
    $config = $this->getCalc()->getDefaultConfig(["A test", "B", "test C", "Test D"]);
    Assert::equal("testWeights:\n    'A test': 100\n    B: 100\n    'test C': 100\n    'Test D': 100\n", $config);
  }

  public function testEmptyDefaultConfig() {
    $config = $this->getCalc()->getDefaultConfig([]);
    Assert::equal("testWeights: {  }\n", $config);
  }

  public function testValidateEmptyWeights() {
    $calc = $this->getCalc();
    $config = $calc->getDefaultConfig([]);
    Assert::true($calc->isScoreConfigValid($config));
  }
}

$testCase = new TestSimpleScoreCalculator();
$testCase->run();
