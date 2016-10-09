<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\ExecutionTaskResult;
use App\Helpers\EvaluationResults\Stats;
use App\Exceptions\ResultsLoadingException;

class TestExecutionTask extends Tester\TestCase
{

  static $sampleStats = [
    "exitcode"  => 0,
    "max-rss"   => 19696,
    "memory"    => 6032,
    "wall-time" => 0.092,
    "exitsig"   => 0,
    "message"   => "This is a random message",
    "status"    => "OK",
    "time"      => 0.037,
    "killed"    => false
  ];

  public function testMissingRequiredParams() {
    Assert::exception(function() { new ExecutionTaskResult([]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new ExecutionTaskResult([ 'task-id' => 'ABC' ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new ExecutionTaskResult([ 'status' => 'XYZ' ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new ExecutionTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ' ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new ExecutionTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'sample_stats' => NULL ]); }, ResultsLoadingException::CLASS);
    Assert::noError(function() { new ExecutionTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'sandbox_results' => self::$sampleStats ]); });
  }

  public function testParsingParams() {
    $result = new ExecutionTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'sandbox_results' => self::$sampleStats ]);
    Assert::same("ABC", $result->getId());
    Assert::same("XYZ", $result->getStatus());
    Assert::equal(new Stats(self::$sampleStats), $result->getStats());
  }

}

# Testing methods run
$testCase = new TestExecutionTask;
$testCase->run();