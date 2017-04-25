<?php

namespace App\V1Module\Presenters;

use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;

use App\Helpers\UploadedFileStorage;
use App\Model\Entity\AdditionalExerciseFile;
use App\Model\Entity\Group;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Assignments;
use App\Model\Repository\UploadedFiles;
use Nette\Application\Responses\FileResponse;

/**
 * Endpoints for management of uploaded files
 */
class UploadedFilesPresenter extends BasePresenter {

  /**
   * @var UploadedFiles
   * @inject
   */
  public $uploadedFiles;

  /**
   * @var UploadedFileStorage
   * @inject
   */
  public $fileStorage;

  /**
   * @var Assignments
   * @inject
   */
  public $assignments;

  /**
   *
   * @param UploadedFile $file
   * @throws ForbiddenRequestException
   */
  private function throwIfUserCantAccessFile(UploadedFile $file) {
    $user = $this->getCurrentUser();

    $isUserSupervisor = FALSE;
    $isFileRelatedToUsersAssignment = FALSE;

    if ($file instanceof AdditionalExerciseFile) {
      foreach ($file->getExercises() as $exercise) {
        if ($this->assignments->isAssignedToUser($exercise, $user)) {
          $isFileRelatedToUsersAssignment = TRUE;
          break;
        }
      }
    }

    /** @var Group $group */
    $group = $this->uploadedFiles->findGroupForFile($file);
    if ($group && ($group->isSupervisorOf($user) || $group->isAdminOf($user))) {
      $isUserSupervisor = TRUE;
    }

    $isUserOwner = $file->getUser()->getId() === $user->getId();

    if (!$isUserOwner && !$isUserSupervisor && !$isFileRelatedToUsersAssignment) {
      throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }
  }

  /**
   * Get details of a file
   * @GET
   * @LoggedIn
   * @UserIsAllowed(files="view-detail")
   * @param string $id Identifier of the uploaded file
   */
  public function actionDetail(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if ($file->isPublic !== TRUE) {
      $this->throwIfUserCantAccessFile($file);
    }
    $this->sendSuccessResponse($file);
  }

  /**
   * Download a file
   * @GET
   * @param string $id Identifier of the file
   */
  public function actionDownload(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if ($file->isPublic !== TRUE) {
      $this->throwIfUserCantAccessFile($file);
    }
    $this->sendResponse(new FileResponse($file->getLocalFilePath(), $file->getName()));
  }

  /**
   * Get the contents of a file
   * @GET
   * @param string $id Identifier of the file
   */
  public function actionContent(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if ($file->isPublic !== TRUE) {
      $this->throwIfUserCantAccessFile($file);
    }
    $this->sendSuccessResponse($file->getContent());
  }


  /**
   * Upload a file
   * @POST
   * @LoggedIn
   * @UserIsAllowed(files="upload")
   */
  public function actionUpload() {
    $user = $this->getCurrentUser();
    $files = $this->getRequest()->getFiles();
    if (count($files) === 0) {
      throw new BadRequestException("No file was uploaded");
    } elseif (count($files) > 1) {
      throw new BadRequestException("Too many files were uploaded");
    }

    $file = array_pop($files);
    $uploadedFile = $this->fileStorage->store($file, $user);
    if ($uploadedFile !== NULL) {
      $this->uploadedFiles->persist($uploadedFile);
      $this->uploadedFiles->flush();
      $this->sendSuccessResponse($uploadedFile);
    } else {
      throw new CannotReceiveUploadedFileException($file->getSanitizedName());
    }
  }
}
