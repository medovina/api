<?php

namespace App\V1Module\Presenters;

use App\Exceptions\InternalServerErrorException;
use App\Exceptions\NotReadyException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\EvaluationLoadingHelper;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\FileServerProxy;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig\Generator as JobConfigGenerator;
use App\Model\Entity\Exercise;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\SubmissionFailure;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Repository\Exercises;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;
use App\Responses\GuzzleResponse;
use App\Security\ACL\IExercisePermissions;
use Tracy\ILogger;

/**
 * Endpoints for manipulation of reference solutions of exercises
 */
class ReferenceExerciseSolutionsPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   * @var UploadedFiles
   * @inject
   */
  public $files;

  /**
   * @var ReferenceExerciseSolutions
   * @inject
   */
  public $referenceSolutions;

  /**
   * @var ReferenceSolutionSubmissions
   * @inject
   */
  public $referenceSubmissions;

  /**
   * @var SubmissionHelper
   * @inject
   */
  public $submissionHelper;

  /**
   * @var HardwareGroups
   * @inject
   */
  public $hardwareGroups;

  /**
   * @var FileServerProxy
   * @inject
   */
  public $fileServerProxy;

  /**
   * @var RuntimeEnvironments
   * @inject
   */
  public $runtimeEnvironments;

  /**
   * @var IExercisePermissions
   * @inject
   */
  public $exerciseAcl;

  /**
   * @var JobConfigGenerator
   * @inject
   */
  public $jobConfigGenerator;

  /**
   * @var EvaluationLoadingHelper
   * @inject
   */
  public $evaluationLoadingHelper;

  /**
   * Get reference solutions for an exercise
   * @GET
   * @param string $exerciseId Identifier of the exercise
   * @throws ForbiddenRequestException
   */
  public function actionExercise(string $exerciseId) {
    $exercise = $this->exercises->findOrThrow($exerciseId);
    if (!$this->exerciseAcl->canViewDetail($exercise)) {
      throw new ForbiddenRequestException("You cannot access this exercise solutions");
    }

    $this->sendSuccessResponse($exercise->getReferenceSolutions()->getValues());
  }

  /**
   * Delete reference solution with given identification.
   * @DELETE
   * @param string $solutionId identifier of reference solution
   * @throws ForbiddenRequestException
   */
  public function actionDeleteReferenceSolution(string $solutionId) {
    $solution = $this->referenceSolutions->findOrThrow($solutionId);
    $exercise = $solution->getExercise();
    if (!$this->exerciseAcl->canDeleteReferenceSolution($exercise, $solution)) {
      throw new ForbiddenRequestException("You cannot delete reference solution of this exercise");
    }

    $this->referenceSolutions->remove($solution);
    $this->referenceSolutions->flush();
    $this->sendSuccessResponse("OK");
  }

  /**
   * Get reference solution evaluations for an exercise solution.
   * @GET
   * @param string $solutionId identifier of the reference exercise solution
   * @throws ForbiddenRequestException
   * @throws InternalServerErrorException
   */
  public function actionEvaluations(string $solutionId) {
    $solution = $this->referenceSolutions->findOrThrow($solutionId);
    $exercise = $solution->getExercise();
    if (!$this->exerciseAcl->canViewDetail($exercise)) {
      throw new ForbiddenRequestException("You cannot access this exercise evaluations");
    }

    /** @var ReferenceSolutionSubmission $submission */
    foreach ($solution->getSubmissions() as $submission) {
      $this->evaluationLoadingHelper->loadEvaluation($submission);
    }

    $this->sendSuccessResponse($solution->getSubmissions()->getValues());
  }

  /**
   * Get reference solution evaluation for an exercise solution.
   * @GET
   * @param string $evaluationId identifier of the reference exercise evaluation
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws InternalServerErrorException
   */
  public function actionEvaluation(string $evaluationId) {
    $submission = $this->referenceSubmissions->findOrThrow($evaluationId);

    $this->evaluationLoadingHelper->loadEvaluation($submission);

    $exercise = $submission->getReferenceSolution()->getExercise();
    if (!$this->exerciseAcl->canViewDetail($exercise)) {
      throw new ForbiddenRequestException("You cannot access this exercise evaluations");
    }

    $this->sendSuccessResponse($submission);
  }

  /**
   * Add new reference solution to an exercise
   * @POST
   * @Param(type="post", name="note", validation="string", description="Description of this particular reference solution, for example used algorithm")
   * @Param(type="post", name="files", description="Files of the reference solution")
   * @Param(type="post", name="runtimeEnvironmentId", description="ID of runtime for this solution")
   * @param string $exerciseId Identifier of the exercise
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws SubmissionEvaluationFailedException
   */
  public function actionCreateReferenceSolution(string $exerciseId) {
    $exercise = $this->exercises->findOrThrow($exerciseId);
    $user = $this->getCurrentUser();

    if (!$this->exerciseAcl->canAddReferenceSolution($exercise)) {
      throw new ForbiddenRequestException("You cannot create reference solutions for this exercise");
    }

    $req = $this->getRequest();
    $note = $req->getPost("note");
    $runtimeId = $req->getPost("runtimeEnvironmentId");

    // detect the runtime configuration
    if ($runtimeId !== NULL) {
      $runtimeEnvironment = $this->runtimeEnvironments->findOrThrow($runtimeId);
    } else {
      throw new NotFoundException("RuntimeConfiguration was not found - automatic detection is not supported");
    }

    // create reference solution
    $referenceSolution = new ReferenceExerciseSolution($exercise, $user, $note, $runtimeEnvironment);

    $uploadedFiles = $this->files->findAllById($req->getPost("files"));
    if (count($uploadedFiles) === 0) {
      throw new SubmissionEvaluationFailedException("No files were uploaded");
    }

    foreach ($uploadedFiles as $file) {
      if (!($file instanceof UploadedFile)) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used in a different submission.");
      }

      $solutionFile = SolutionFile::fromUploadedFile($file, $referenceSolution->getSolution());
      $this->files->persist($solutionFile, FALSE);
      $this->files->remove($file, FALSE);
    }

    $this->referenceSolutions->persist($referenceSolution);
    $this->sendSuccessResponse($referenceSolution);
  }

  /**
   * Evaluate a single reference exercise solution for all configured hardware groups
   * @POST
   * @param string $id Identifier of the reference solution
   * @Param(type="post", name="debug", validation="bool", required=false, "Debugging evaluation with all logs and outputs")
   * @throws ForbiddenRequestException
   */
  public function actionEvaluate(string $id) {
    $req = $this->getRequest();
    $isDebug = filter_var($req->getPost("debug"), FILTER_VALIDATE_BOOLEAN);

    /** @var ReferenceExerciseSolution $referenceSolution */
    $referenceSolution = $this->referenceSolutions->findOrThrow($id);

    if (!$this->exerciseAcl->canEvaluateReferenceSolution($referenceSolution->getExercise(), $referenceSolution)) {
      throw new ForbiddenRequestException();
    }

    list($evaluations, $errors) = $this->evaluateReferenceSolution($referenceSolution, $isDebug);

    $this->sendSuccessResponse([
      "referenceSolution" => $referenceSolution,
      "evaluations" => $evaluations,
      "errors" => $errors
    ]);
  }

  /**
   * Evaluate all reference solutions for an exercise (and for all configured hardware groups).
   * @POST
   * @param string $exerciseId Identifier of the exercise
   * @Param(type="post", name="debug", validation="bool", required=false, "Debugging evaluation with all logs and outputs")
   * @throws ForbiddenRequestException
   */
  public function actionEvaluateForExercise($exerciseId) {
    $req = $this->getRequest();
    $isDebug = filter_var($req->getPost("debug"), FILTER_VALIDATE_BOOLEAN);

    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($exerciseId);
    $result = [];

    if (!$this->exerciseAcl->canEvaluateReferenceSolution($exercise, null)) {   // null for a solution means all solutions whatsoever
      throw new ForbiddenRequestException();
    }

    foreach ($exercise->getReferenceSolutions() as $referenceSolution) {
      list($evaluations, $errors) = $this->evaluateReferenceSolution($referenceSolution, $isDebug);
      $result[] = [
        "referenceSolution" => $referenceSolution,
        "evaluations" => $evaluations,
        "errors" => $errors
      ];
    }

    $this->sendSuccessResponse($result);
  }

  /**
   * @param ReferenceExerciseSolution $referenceSolution
   * @param bool $isDebug
   * @return array
   * @throws ForbiddenRequestException
   */
  private function evaluateReferenceSolution(
      ReferenceExerciseSolution $referenceSolution,
      bool $isDebug = false
  ): array {
    $exercise = $referenceSolution->getExercise();
    $runtimeEnvironment = $referenceSolution->getRuntimeEnvironment();
    $hwGroups = $exercise->getHardwareGroups();
    $evaluations = [];
    $errors = [];
    $submittedFiles = array_map(function(UploadedFile $file) { return $file->getName(); }, $referenceSolution->getFiles()->getValues());

    $compilationParams = CompilationParams::create($submittedFiles, $isDebug);
    $generatorResults = $this->jobConfigGenerator
      ->generateJobConfig($this->getCurrentUser(), $exercise, $runtimeEnvironment, $compilationParams);

    foreach ($hwGroups->getValues() as $hwGroup) {
      // create the entity and generate the ID
      $evaluation = new ReferenceSolutionSubmission($referenceSolution, $hwGroup,
        $generatorResults->getJobConfigPath(), $this->getCurrentUser());
      $this->referenceSubmissions->persist($evaluation);

      try {
        $resultsUrl = $this->submissionHelper->submitReference(
          $evaluation->getId(),
          $runtimeEnvironment->getId(),
          $hwGroup->getId(),
          $referenceSolution->getFiles()->getValues(),
          $generatorResults->getJobConfig()
        );

        $evaluation->setResultsUrl($resultsUrl);
        $this->referenceSubmissions->flush();
        $evaluations[] = $evaluation;
      } catch (SubmissionFailedException $e) {
        $this->logger->log("Reference evaluation exception: " . $e->getMessage(), ILogger::EXCEPTION);
        $failure = SubmissionFailure::forReferenceSubmission(SubmissionFailure::TYPE_BROKER_REJECT, $e->getMessage(), $evaluation);
        $this->referenceSubmissions->persist($failure, false);
        $errors[] = $hwGroup->getId();
      }
    }

    if (count($errors) > 0) {
      $this->referenceSubmissions->flush();
    }

    return [$evaluations, $errors];
  }

  /**
   * Download result archive from backend for a reference solution evaluation
   * @GET
   * @param string $evaluationId
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws NotReadyException
   * @throws InternalServerErrorException
   * @throws \Nette\Application\AbortException
   */
  public function actionDownloadResultArchive(string $evaluationId) {
    /** @var ReferenceSolutionSubmission $submission */
    $submission = $this->referenceSubmissions->findOrThrow($evaluationId);
    $refSolution = $submission->getReferenceSolution();

    if (!$this->exerciseAcl->canEvaluateReferenceSolution($refSolution->getExercise(), $refSolution)) {
      throw new ForbiddenRequestException();
    }

    $this->evaluationLoadingHelper->loadEvaluation($submission);

    if (!$submission->hasEvaluation()) {
      throw new NotReadyException("Submission is not evaluated yet");
    }

    $stream = $this->fileServerProxy->getResultArchiveStream($submission->getResultsUrl());
    if ($stream === null) {
      throw new NotFoundException("Archive for solution evaluation '$evaluationId' not found on remote fileserver");
    }

    $this->sendResponse(new GuzzleResponse($stream, $evaluationId . '.zip', "application/zip"));
  }
}
