<?php

namespace App\V1Module\Presenters;

use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use App\Exceptions\SubmissionFailedException;

use App\Model\Entity\Submission;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig;
use App\Model\Repository\ExerciseAssignments;
use App\Model\Repository\Submissions;
use App\Model\Repository\UploadedFiles;

/**
 * @LoggedIn
 */
class ExerciseAssignmentsPresenter extends BasePresenter {

  /** @inject @var ExerciseAssignments */
  public $assignments;

  /** @inject @var Submissions */
  public $submissions;

  /** @inject @var UploadedFiles */
  public $files;

  /** @inject @var SubmissionHelper */
  public $submissionHelper;

  protected function findAssignmentOrThrow(string $id) {
    $assignment = $this->assignments->get($id);
    if (!$assignment) {
      throw new NotFoundException;
    }

    return $assignment;
  }

  /**
   * @GET
   */
  public function actionDefault() {
    $assignments = $this->assignments->findAll();
    $user = $this->users->findOrThrow('me');
    $personalizedData = $assignments->map(
      function ($assignment) {
        return $assignment->getJsonData($user);
      }
    );
    $this->sendSuccessResponse($personalizedData);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    // @todo: check if the user can access this information
    $assignment = $this->findAssignmentOrThrow($id);
    $this->sendSuccessResponse($assignment);
  }

  /**
   * @GET
   */
  public function actionCanSubmit(string $id) {
    // @todo: check if the user can access this information
    $assignment = $this->findAssignmentOrThrow($id);
    $user = $this->users->findOrThrow('me');
    $this->sendSuccessResponse($assignment->canReceiveSubmissions($user));
  }

  /**
   * @GET
   */
  public function actionSubmissions(string $id, string $userId) {
    $assignment = $this->findAssignmentOrThrow($id);
    $submissions = $this->submissions->findSubmissions($assignment, $userId);
    $this->sendSuccessResponse($submissions);
  }

  /**
   * @POST
   * @Param(type="post", name="note")
   * @Param(type="post", name="files")
   */
  public function actionSubmit(string $id) {
    $assignment = $this->findAssignmentOrThrow($id);
    $req = $this->getHttpRequest();

    $loggedInUser = $this->users->findOrThrow("me");
    $userId = $req->getPost("userId");
    if ($userId !== NULL) {
      $user = $this->users->findOrThrow($userId);
    } else {
      $user = $loggedInUser;
    }

    if (!$assignment->canReceiveSubmissions($loggedInUser)) {
      throw new ForbiddenRequestException("User '{$loggedInUser->getId()}' cannot submit solutions for this exercise any more.");
    }

    // create the submission record
    $hwGroup = "group1";
    $files = $this->files->findAllById($req->getPost("files"));
    $note = $req->getPost("note");
    $submission = Submission::createSubmission($note, $assignment, $user, $loggedInUser, $hwGroup, $files);

    // persist all the data in the database - this will also assign the UUID to the submission
    $this->submissions->persist($submission);

    // get the job config with correct job id
    $path = $submission->getExerciseAssignment()->getJobConfigFilePath();
    $jobConfig = JobConfig\Loader::getJobConfig($path); 
    $jobConfig->setJobId($submission->getId());

    $resultsUrl = $this->submissionHelper->initiateEvaluation(
      $jobConfig,
      $submission->getFiles()->toArray(),
      $hwGroup
    );

    if($resultsUrl !== NULL) {
      $submission->setResultsUrl($resultsUrl);
      $this->submissions->persist($submission);
      $this->sendSuccessResponse([
        "submission" => $submission,
        "webSocketChannel" => [
          "id" => $jobConfig->getJobId(),
          "monitorUrl" => $this->getContext()->parameters['monitor']['address'],
          "expectedTasksCount" => $jobConfig->getTasksCount()
        ],
      ]);
    } else {
      throw new SubmissionFailedException;
    }
  }
}
