<?php

namespace App\Helpers;

use App\Model\Entity\UploadedFile;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Arrays;

use App\Exceptions\SubmissionFailedException;
use App\Exceptions\SubmissionEvaluationFailedException;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ZipArchive;

/**
 * Helper class for communication with dedicated fileserver. The fileserver uses HTTP Basic Auth,
 * so proper credentials must be set in configuration of the API.
 */
class FileServerProxy {

  const JOB_CONFIG_FILENAME = "job-config.yml";
  const TASKS_ROUTE = "/exercises";

  /** @var string Address of the remote fileserver (including port) */
  private $remoteServerAddress;

  /** @var Client Standard HTTP PHP client */
  private $client;

  /**
   * Constructor, initialization of configurable options
   * @param array $config Configuration
   */
  public function __construct(array $config) {
    $this->remoteServerAddress = Arrays::get($config, "address");
    $this->client = new Client([
      "base_uri" => $this->remoteServerAddress,
      "auth" => [
        Arrays::get($config, ["auth", "username"], "re"),
        Arrays::get($config, ["auth", "password"], "codex")
      ],
      "connect_timeout" => floatval(Arrays::get($config, ["timeouts", "connection"], 1000)) / 1000.0,
      "timeout" => floatval(Arrays::get($config, ["timeouts", "request"], 30000)) / 1000.0
    ]);
  }

  /**
   * Gets URL of fileserver which can be used from worker to download data during execution
   * @return string URL of the fileserver
   */
  public function getFileserverTasksUrl(): string {
      return $this->remoteServerAddress . self::TASKS_ROUTE;
  }

  /**
   * Downloads the contents of a file and return it in form of guzzle stream.
   * <p>
   * Returning whole body is fine guzzle uses smart streams
   * which will save content to filesystem if it is too big.
   * But this must be considered when using it!
   * </p>
   * @param   string $url   URL of the file
   * @return  StreamInterface|null Stream with contents of the archive
   */
  public function getFileserverFileStream(string $url) {
    return $this->getRequest($url);
  }

  /**
   * Download file from files server directly to temporary file, path of newly
   * created file will be returned.
   * @param string $url
   * @return null|string ?string local path
   */
  public function downloadFile(string $url): ?string {
    try {
      $tmpFile = tempnam(sys_get_temp_dir(), "ReC");
      $this->client->request("GET", $url, [
        "sink" => $tmpFile
      ]);
      return $tmpFile;
    } catch (ClientException $e) {
      return null;
    }
  }

  /**
   * Downloads the contents of a archive file at the given URL and return
   * unparsed YAML results of evaluation.
   * @param   string $url   URL of the file
   * @return  string|null   Contents of the results file
   * @throws  SubmissionEvaluationFailedException when results are not available
   */
  public function downloadResults(string $url) {
    $result = $this->downloadFile($url);

    if (!$result) {
      return null; // Results are probably not ready yet
    }

    $content = $this->getResultYmlContent($result);
    unlink($result);

    return $content;
  }

  /**
   * Downloads the contents of a file with GET request and return it in form of guzzle stream.
   * <p>
   * Returning whole body is fine guzzle uses smart streams
   * which will save content to filesystem if it is too big.
   * But this must be considered when using it!
   * </p>
   * @param   string $url   URL of the file
   * @return  StreamInterface|null Stream with contents of the archive
   */
  private function getRequest(string $url) {
    try {
      $response = $this->client->request("GET", $url);
    } catch (ClientException $e) {
      return null;
    }
    return $response->getBody();
  }

  /**
   * Extracts the contents of the downloaded ZIP file and return unparsed YAML results
   * @param   string $zipPath Location of the downloaded ZIP file
   * @return  string Content of the results file
   * @throws  SubmissionEvaluationFailedException when archive is corrupted or malformed
   */
  private function getResultYmlContent(string $zipPath): string {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
      throw new SubmissionEvaluationFailedException("Cannot open results from remote file server.");
    }

    $yml = $zip->getFromName("result/result.yml");
    if ($yml === false) {
      throw new SubmissionEvaluationFailedException("Results YAML file is missing in the archive received from remote FS.");
    }

    // a bit of a cleanup
    $zip->close();

    return $yml;
  }

  /**
   * Send files submitted as a solution to an assignment to the fileserver
   * @param string   $jobId     Identifier of job for which these files are intended
   * @param string   $jobConfig Content of job configuration for this submission
   * @param UploadedFile[] $files Files submitted by user
   * @return mixed   List of archive URL at fileserver and URL where results needs to be stored to
   * @throws SubmissionFailedException on any error
   */
  public function sendFiles(string $jobId, string $jobConfig, array $files) {
    $filesToSubmit = $this->prepareFiles($jobConfig, $files);

    try {
      $response = $this->client->request(
        "POST",
        "/submissions/$jobId",
        [ "multipart" => $filesToSubmit ]
      );

      $paths = $this->decodeJsonResponse($response);

      if (!isset($paths->archive_path) || !isset($paths->result_path)) {
        throw new SubmissionFailedException("Remote file server broke the communication protocol");
      }

      return [
        $this->remoteServerAddress . $paths->archive_path,
        $this->remoteServerAddress . $paths->result_path
      ];
    } catch (RequestException $e) {
      throw new SubmissionFailedException("Cannot connect to remote file server - {$e->getMessage()}");
    }
  }

  /**
   * Send supplementary task files (e.g. test inputs) to the file server
   * @param UploadedFile[] $files files to be uploaded
   * @return array
   * @throws SubmissionFailedException
   */
  public function sendSupplementaryFiles(array $files) {
    $fileData = array_map([$this, 'prepareSupplementaryFileData'], $files);
    $httpResponse = $this->client->request("POST", self::TASKS_ROUTE, [
      'multipart' => $fileData
    ]);
    $response = $this->decodeJsonResponse($httpResponse);

    if (!isset($response->files)) {
      throw new SubmissionFailedException("Remote file server broke the communication protocol");
    }

    $result = [];

    foreach ($response->files as $name => $path) {
      $result[$name] = $this->remoteServerAddress . $path;
    }

    return $result;
  }

  /**
   * @param UploadedFile $file
   * @return array
   */
  private function prepareSupplementaryFileData(UploadedFile $file) {
    return [
      "name" => $file->getName(),
      "filename" => $file->getName(),
      "contents" => $file->getContent()
    ];
  }

  /**
   * Decode a JSON-encoded HTTP response body
   * @param ResponseInterface $response
   * @return mixed
   * @throws SubmissionFailedException
   */
  private function decodeJsonResponse(ResponseInterface $response) {
    if ($response->getStatusCode() !== 200) {
      throw new SubmissionFailedException("Request on file server failed");
    }

    try {
      return Json::decode($response->getBody());
    } catch (JsonException $e) {
      throw new SubmissionFailedException("Fileserver did not respond with a valid JSON response - {$e->getMessage()}");
    }
  }

  /**
   * Convert an uploaded file to an array that can be passed to Guzzle
   * @param $file
   * @return array
   * @throws SubmissionFailedException
   */
  private function prepareFileData(UploadedFile $file) {
      if (!file_exists($file->getLocalFilePath())) {
        throw new SubmissionFailedException("File {$file->getLocalFilePath()} does not exist on the server.");
      }

      return [
        "name" => $file->getName(),
        "filename" => $file->getName(),
        "contents" => fopen($file->getLocalFilePath(), "r")
      ];
  }

  /**
   * Prepare files for sending to fileserver.
   * @param string $jobConfig Content of job configuration
   * @param array  $files     Paths to user submitted files
   * @return array Files including name and content of each one
   * @throws SubmissionFailedException if any of files is not available or name of any file is prohibited
   */
  private function prepareFiles(string $jobConfig, array $files) {
    $filesToSubmit = array_map(function ($file) {
      if ($file->name === self::JOB_CONFIG_FILENAME) {
        throw new SubmissionFailedException("User is not allowed to upload a file with the name of " . self::JOB_CONFIG_FILENAME);
      }

      return $this->prepareFileData($file);
    }, $files);

    // the job config must be among the uploaded files as well
    $filesToSubmit[] = [
      "name" => self::JOB_CONFIG_FILENAME,
      "filename" => self::JOB_CONFIG_FILENAME,
      "contents" => $jobConfig
    ];

    return $filesToSubmit;
  }

}
