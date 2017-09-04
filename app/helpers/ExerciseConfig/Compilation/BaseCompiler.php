<?php

namespace App\Helpers\ExerciseConfig\Compilation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\JobConfig\JobConfig;


/**
 * Internal exercise configuration compilation service.
 */
class BaseCompiler {

  /**
   * @var PipelinesMerger
   */
  private $pipelinesMerger;

  /**
   * @var BoxesSorter
   */
  private $boxesSorter;

  /**
   * @var BoxesOptimizer
   */
  private $boxesOptimizer;

  /**
   * @var BoxesCompiler
   */
  private $boxesCompiler;

  /**
   * @var TestDirectoriesResolver
   */
  private $testDirectoriesResolver;

  /**
   * ExerciseConfigValidator constructor.
   * @param PipelinesMerger $pipelinesMerger
   * @param BoxesSorter $boxesSorter
   * @param BoxesOptimizer $boxesOptimizer
   * @param BoxesCompiler $boxesCompiler
   * @param TestDirectoriesResolver $testDirectoriesResolver
   */
  public function __construct(PipelinesMerger $pipelinesMerger,
      BoxesSorter $boxesSorter, BoxesOptimizer $boxesOptimizer,
      BoxesCompiler $boxesCompiler, TestDirectoriesResolver $testDirectoriesResolver) {
    $this->pipelinesMerger = $pipelinesMerger;
    $this->boxesSorter = $boxesSorter;
    $this->boxesOptimizer = $boxesOptimizer;
    $this->boxesCompiler = $boxesCompiler;
    $this->testDirectoriesResolver = $testDirectoriesResolver;
  }


  /**
   * Compile ExerciseConfig to JobConfig
   * @param ExerciseConfig $exerciseConfig
   * @param VariablesTable $environmentConfigVariables
   * @param ExerciseLimits[] $limits
   * @param string $runtimeEnvironmentId
   * @param string[] $submittedFiles
   * @return JobConfig
   */
  public function compile(ExerciseConfig $exerciseConfig,
      VariablesTable $environmentConfigVariables, array $limits,
      string $runtimeEnvironmentId, array $submittedFiles): JobConfig {
    $tests = $this->pipelinesMerger->merge($exerciseConfig, $environmentConfigVariables, $runtimeEnvironmentId, $submittedFiles);
    $sortedTests = $this->boxesSorter->sort($tests);
    $optimized = $this->boxesOptimizer->optimize($sortedTests);
    $testDirectories = $this->testDirectoriesResolver->resolve($optimized);
    $jobConfig = $this->boxesCompiler->compile($testDirectories, $limits);
    return $jobConfig;
  }

}