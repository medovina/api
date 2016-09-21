<?php

namespace App\Helpers\EvaluationResults;

use App\Exceptions\ResultsLoadingException;
use App\Helpers\JobConfig\JobConfig;

use Symfony\Component\Yaml\Yaml;

class EvaluationResults {

  /** @var array Raw data from the results */
  private $data;

  /** @var array Assoc array of the tasks */
  private $tasks = [];

  /** @var JobConfig The configuration of the job */
  private $config;

  /** @var bool */
  private $initOK = TRUE;

  public function __construct(array $data, JobConfig $config) {
    if (!isset($data["job-id"])) {
      throw new ResultsLoadingException("Job ID is not set in the result.");
    }

    if ($data["job-id"] !== $config->getJobId()) {
      throw new ResultsLoadingException("Job ID of the configuration and the result do not match.");
    }

    if (!isset($data["results"])) {
      throw new ResultsLoadingException("Results are missing required field 'results'.");
    }

    if (!is_array($data["results"])) {
      throw new ResultsLoadingException("Results field of the results must be an array.");
    }

    $this->config = $config;
    $this->data = $data;

    // store all the reported results
    $this->tasks = [];
    foreach ($data["results"] as $task) {
      if (!isset($task["task-id"])) {
        throw new ResultsLoadingException("One of the task's result is missing 'task-id'");
      }

      $taskId = $task["task-id"];
      $this->tasks[$taskId] = new TaskResult($task);
    }

    // test if all the tasks in the config file have corresponding results
    // and also check if all the initiation tasks were successful
    // - missing task results are replaced with skipped task results
    foreach ($this->config->getTasks() as $taskCfg) {
      $id = $taskCfg->getId();
      if (!isset($this->tasks[$id])) {
        $this->tasks[$id] = new SkippedTaskResult($id);
      }

      if ($taskCfg->isInitiationTask() && !$this->tasks[$id]->isOK()) {
        $this->initOK = FALSE;
      }
    }
  }

  public function initOK() {
    return $this->initOK;
  }

  public function getTestsResults($hardwareGroupId) {
    return array_map(
      function($test) use ($hardwareGroupId) {
        $execId = $test->getExecutionTask()->getId();
        $evalId = $test->getEvaluationTask()->getId();
        $exec = $this->tasks[$execId]->getAsExecutionTaskResult();
        $eval = $this->tasks[$execId]->getAsEvaluationTaskResult();

        switch (TestResult::calculateStatus($exec->getStatus(), $eval->getStatus())) {
          case TestResult::STATUS_OK:
            return new TestResult($test, $exec, $eval, $hardwareGroupId);
          case TestResult::STATUS_SKIPPED:
            return new SkippedTestResult($test);
          default:
            return new FailedTestResult($test);
        }
      },
      $this->config->getTests($hardwareGroupId)
    );
  }

  public function __toString() {
    return Yaml::dump($this->data);
  }

}
