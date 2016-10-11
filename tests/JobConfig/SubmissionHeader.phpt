<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\SubmissionHeader;
use Symfony\Component\Yaml\Yaml;


class TestSubmissionHeader extends Tester\TestCase
{
  static $minimalConfig = [
    "job-id" => "id123",
    "file-collector" => "https://collector",
    "language" => "cpp"
  ];

  public function testValidConstructionRequired() {
    $header = new SubmissionHeader(self::$minimalConfig);
    Assert::equal("id123", $header->getId());
    Assert::equal("student", $header->getType());
    Assert::equal("student_id123", $header->getJobId());
    Assert::false($header->getLog());
  }

  public function testValidConstructionAdditional() {
    $config = self::$minimalConfig;
    $config["somekey"] = "somevalue";
    $config["otherkey"] = "othervalue";
    $header = new SubmissionHeader($config);
    Assert::equal(array("somekey" => "somevalue", "otherkey" => "othervalue"), $header->getAdditionalData());
  }

  public function testInvalidJobId() {
    $config = self::$minimalConfig;
    $config["job-id"] = "wrtype_id";
    Assert::exception(
      function() use ($config) {
        new SubmissionHeader($config);
      }, JobConfigLoadingException::CLASS
    );
  }

  public function testSetJobId() {
    $header = new SubmissionHeader(self::$minimalConfig);
    Assert::equal("id123", $header->getId());
    $header->setId("mojeid");
    Assert::equal("mojeid", $header->getId());

    $header->setType("reference");
    Assert::equal("reference", $header->getType());

    Assert::equal("reference_mojeid", $header->getJobId());
    Assert::exception(
      function() use ($header) {
        $header->setType("unknown");
      }, JobConfigLoadingException::CLASS
    );
  }

  public function testSetFileCollector() {
    $header = new SubmissionHeader(self::$minimalConfig);
    Assert::equal("https://collector", $header->getFileCollector());
    $header->setFileCollector("https://new.collector");
    Assert::equal("https://new.collector", $header->getFileCollector());
  }

  public function testSetLanguage() {
    $header = new SubmissionHeader(self::$minimalConfig);
    Assert::equal("cpp", $header->getLanguage());
    $header->setLanguage("newspeak");
    Assert::equal("newspeak", $header->getLanguage());
  }

  public function testSetLog() {
    $header = new SubmissionHeader(self::$minimalConfig);
    Assert::false($header->getLog());
    $header->setLog(TRUE);
    Assert::true($header->getLog());
  }

  public function testToArray() {
    $config = self::$minimalConfig;
    $config["somekey"] = "somevalue";
    $config["otherkey"] = "othervalue";
    $header = new SubmissionHeader($config);

    $expected = [
      "job-id" => "student_id123",
      "file-collector" => "https://collector",
      "language" => "cpp",
      "log" => "false",
      "somekey" => "somevalue",
      "otherkey" => "othervalue"
    ];
    Assert::equal($expected, $header->toArray());
  }
}

# Testing methods run
$testCase = new TestSubmissionHeader;
$testCase->run();
