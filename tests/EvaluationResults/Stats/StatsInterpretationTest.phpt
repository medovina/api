<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\Stats;
use App\Helpers\EvaluationResults\StatsInterpretation;
use App\Helpers\JobConfig\Builder;
use App\Helpers\JobConfig\Limits;

class TestStatsInterpretation extends Tester\TestCase
{

  static $limitsSample = [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ];
  static $statsSample = [
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

  /** @var Builder */
  private $builder;

  public function __construct() {
    $this->builder = new Builder;
  }

  public function testTimeUnused() {
    $stats = new Stats(self::$statsSample);
    $limits = $this->builder->buildLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "time" => 0.1 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(TRUE, $interpretation->isTimeOK());
    Assert::equal(0.037 / 0.1, $interpretation->getUsedTimeRatio());
  }

  public function testTimeSame() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 0.1 ]));
    $limits = $this->builder->buildLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "time" => 0.1 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(TRUE, $interpretation->isTimeOK());
    Assert::equal(1.0, $interpretation->getUsedTimeRatio());
  }

  public function testTimeExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 0.1 ]));
    $limits = $this->builder->buildLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "time" => 0.037 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(FALSE, $interpretation->isTimeOK());
    Assert::equal(0.1 / 0.037, $interpretation->getUsedTimeRatio());
  }

  public function testMemoryUnused() {
    $stats = new Stats(array_merge(self::$statsSample, [ "memory" => 128 ]));
    $limits = $this->builder->buildLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "memory" => 256 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(TRUE, $interpretation->isMemoryOK());
    Assert::equal(0.5, $interpretation->getUsedMemoryRatio());
  }

  public function testMemorySame() {
    $stats = new Stats(array_merge(self::$statsSample, [ "memory" => 128 ]));
    $limits = $this->builder->buildLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "memory" => 128 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(TRUE, $interpretation->isMemoryOK());
    Assert::equal(1.0, $interpretation->getUsedMemoryRatio());
  }

  public function testMemoryExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "memory" => 256 ]));
    $limits = $this->builder->buildLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "memory" => 128 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(FALSE, $interpretation->isMemoryOK());
    Assert::equal(2.0, $interpretation->getUsedMemoryRatio());
  }

  public function testBothOK() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 1, "memory" => 64 ]));
    $limits = $this->builder->buildLimits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(TRUE, $interpretation->doesMeetAllCriteria());
  }

  public function testBothExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 3, "memory" => 2560 ]));
    $limits = $this->builder->buildLimits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(FALSE, $interpretation->doesMeetAllCriteria());
  }

  public function testOnlyTimeExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 3, "memory" => 1 ]));
    $limits = $this->builder->buildLimits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(FALSE, $interpretation->doesMeetAllCriteria());
  }

  public function testOnlyMemoryExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 1, "memory" => 2560 ]));
    $limits = $this->builder->buildLimits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(FALSE, $interpretation->doesMeetAllCriteria());
  }


}

# Testing methods run
$testCase = new TestStatsInterpretation;
$testCase->run();
