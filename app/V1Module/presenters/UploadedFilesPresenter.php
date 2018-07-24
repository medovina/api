<?php

namespace App\V1Module\Presenters;

use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;

use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\FileServerProxy;
use App\Helpers\UploadedFileStorage;
use App\Helpers\UploadsConfig;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Assignments;
use App\Model\Repository\SupplementaryExerciseFiles;
use App\Model\Repository\UploadedFiles;
use App\Responses\GuzzleResponse;
use App\Security\ACL\IUploadedFilePermissions;
use Nette\Application\Responses\FileResponse;
use Nette\Application\IResponse;
use Nette\Utils\Strings;

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
   * @var IUploadedFilePermissions
   * @inject
   */
  public $uploadedFileAcl;

  /**
   * @var SupplementaryExerciseFiles
   * @inject
   */
  public $supplementaryFiles;

  /**
   * @var FileServerProxy
   * @inject
   */
  public $fileServerProxy;

  /**
   * @var UploadsConfig
   * @inject
   */
  public $uploadsConfig;

  public function checkDetail(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if (!$this->uploadedFileAcl->canViewDetail($file)) {
      throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }
  }

  /**
   * Get details of a file
   * @GET
   * @LoggedIn
   * @param string $id Identifier of the uploaded file
   */
  public function actionDetail(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    $this->sendSuccessResponse($file);
  }

  public function checkDownload(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if (!$this->uploadedFileAcl->canDownload($file)) {
      throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }
  }

  /**
   * Download a file
   * @GET
   * @param string $id Identifier of the file
   * @throws \Nette\Application\AbortException
   * @throws \Nette\Application\BadRequestException
   */
  public function actionDownload(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    $this->sendResponse($this->getFileResponse($file));
  }

  public function checkContent(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    if (!$this->uploadedFileAcl->canDownload($file)) {
      throw new ForbiddenRequestException("You are not allowed to access file '{$file->getId()}");
    }
  }

  /**
   * Get the contents of a file
   * @GET
   * @param string $id Identifier of the file
   */
  public function actionContent(string $id) {
    $file = $this->uploadedFiles->findOrThrow($id);
    $sizeLimit = $this->uploadsConfig->getMaxPreviewSize();
    $content = $this->getFileContents($file, $sizeLimit);

    // Remove UTF BOM prefix...
    $utf8bom = "\xef\xbb\xbf";
    $content = Strings::replace($content, "~^$utf8bom~");

    $fixedContent = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

    $this->sendSuccessResponse([
      "content" => $fixedContent,
      "malformedCharacters" => $fixedContent !== $content,
      "tooLarge" => $file->getFileSize() > $sizeLimit,
    ]);
  }

  public function checkUpload() {
    if (!$this->uploadedFileAcl->canUpload()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Upload a file
   * @POST
   * @throws InvalidArgumentException for files with invalid names
   * @throws ForbiddenRequestException
   * @throws BadRequestException
   * @throws CannotReceiveUploadedFileException
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

    if ($uploadedFile === null) {
      throw new CannotReceiveUploadedFileException($file->getName());
    }

    $this->uploadedFiles->persist($uploadedFile);
    $this->uploadedFiles->flush();
    $this->sendSuccessResponse($uploadedFile);
  }

  public function checkDownloadSupplementaryFile(string $id) {
    $file = $this->supplementaryFiles->findOrThrow($id);
    if (!$this->uploadedFileAcl->canDownloadSupplementaryFile($file)) {
      throw new ForbiddenRequestException("You are not allowed to download file '{$file->getId()}");
    }
  }

  /**
   * Download supplementary file
   * @GET
   * @param string $id Identifier of the file
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws \Nette\Application\AbortException
   */
  public function actionDownloadSupplementaryFile(string $id) {
    $file = $this->supplementaryFiles->findOrThrow($id);

    $stream = $this->fileServerProxy->getFileserverFileStream($file->getFileServerPath());
    if ($stream === null) {
      throw new NotFoundException("Supplementary file '$id' not found on remote fileserver");
    }

    $this->sendResponse(new GuzzleResponse($stream, $file->getName()));
  }

  protected function getFileContents(UploadedFile $file, int $sizeLimit = null) {
    if ($file instanceof SolutionFile || $file instanceof SupplementaryExerciseFile) {
      $stream = $this->fileServerProxy->getFileserverFileStream($this->fileServerProxy->getFileserverUrl() . $file->getFileServerPath());
      if ($stream === null) {
        throw new NotFoundException("File '{$file->getId()}' not found on remote fileserver");
      }
      return $stream->read($sizeLimit === null ? $stream->getSize() : $sizeLimit);
    }

    return $file->getContent($sizeLimit);
  }

  /**
   * @param UploadedFile $file
   * @return IResponse
   * @throws \Nette\Application\BadRequestException
   */
  protected function getFileResponse(UploadedFile $file): IResponse {
    if ($file instanceof SolutionFile || $file instanceof SupplementaryExerciseFile) {
      $stream = $this->fileServerProxy->getFileserverFileStream($this->fileServerProxy->getFileserverUrl() . $file->getFileServerPath());
      if ($stream === null) {
        throw new NotFoundException("File '{$file->getId()}' not found on remote fileserver");
      }
      return new GuzzleResponse($stream, $file->getName());
    }

    return new FileResponse($file->getLocalFilePath(), $file->getName());
  }
}
