<?php

namespace App\Helpers\JobConfig\Tasks;
use App\Exceptions\JobConfigLoadingException;


/**
 * Holder for task which has evaluation type set.
 */
class EvaluationTaskType {
  /** Evaluation task type value */
  const TASK_TYPE = "evaluation";

  /** @var TaskBase Evaluation task */
  private $task;

  /**
   * Checks and store evaluation task.
   * @param TaskBase $task
   * @throws JobConfigLoadingException
   */
  public function __construct(TaskBase $task) {
    if (!$task->isEvaluationTask()) {
      throw new JobConfigLoadingException("Given task does not have type '" . self::TASK_TYPE . "'");
    }

    $this->task = $task;
  }

  /**
   * Get evaluation task which was given and checked during construction.
   * @return TaskBase
   */
  public function getTask(): TaskBase {
    return $this->task;
  }

}