<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\AssignmentsPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;

class TestAssignmentsPresenter extends Tester\TestCase
{
  /** @var AssignmentsPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var App\Model\Repository\Assignments */
  protected $assignments;

  /** @var Nette\Security\User */
  private $user;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->assignments = $container->getByType(App\Model\Repository\Assignments::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, AssignmentsPresenter::class);
    $this->presenter->submissionHelper = Mockery::mock(App\Helpers\SubmissionHelper::class);
    $this->presenter->monitorConfig = new App\Helpers\MonitorConfig(['address' => 'localhost']);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testListAssignments()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Assignments', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->presenter->assignments->findAll(), $result['payload']);
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $assignments = $this->assignments->findAll();
    $assignment = array_pop($assignments);

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'detail', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($assignment, $result['payload']);
  }

  public function testUpdateDetail()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $assignments = $this->assignments->findAll();
    $assignment = array_pop($assignments);

    $name = "newAssignmentName";
    $isPublic = true;
    $localizedAssignments = [
      [ "locale" => "locA", "description" => "descA", "name" => "nameA" ]
    ];
    $firstDeadline = (new \DateTime())->getTimestamp();
    $maxPointsBeforeFirstDeadline = 123;
    $submissionsCountLimit = 321;
    $scoreConfig = "scoreConfiguration in yaml";
    $allowSecondDeadline = true;
    $canViewLimitRatios = false;
    $secondDeadline = (new \DateTime)->getTimestamp();
    $maxPointsBeforeSecondDeadline = 543;

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'updateDetail', 'id' => $assignment->getId()],
      [
        'name' => $name,
        'isPublic' => $isPublic,
        'localizedAssignments' => $localizedAssignments,
        'firstDeadline' => $firstDeadline,
        'maxPointsBeforeFirstDeadline' => $maxPointsBeforeFirstDeadline,
        'submissionsCountLimit' => $submissionsCountLimit,
        'scoreConfig' => $scoreConfig,
        'allowSecondDeadline' => $allowSecondDeadline,
        'canViewLimitRatios' => $canViewLimitRatios,
        'secondDeadline' => $secondDeadline,
        'maxPointsBeforeSecondDeadline' => $maxPointsBeforeSecondDeadline
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    // check updated assignment
    $updatedAssignment = $result['payload'];
    Assert::type(\App\Model\Entity\Assignment::class, $updatedAssignment);
    Assert::equal($name, $updatedAssignment->getName());
    Assert::equal($isPublic, $updatedAssignment->getIsPublic());
    Assert::equal($firstDeadline, $updatedAssignment->getFirstDeadline()->getTimestamp());
    Assert::equal($maxPointsBeforeFirstDeadline, $updatedAssignment->getMaxPointsBeforeFirstDeadline());
    Assert::equal($submissionsCountLimit, $updatedAssignment->getSubmissionsCountLimit());
    Assert::equal($scoreConfig, $updatedAssignment->getScoreConfig());
    Assert::equal($allowSecondDeadline, $updatedAssignment->getAllowSecondDeadline());
    Assert::equal($canViewLimitRatios, $updatedAssignment->getCanViewLimitRatios());
    Assert::equal($secondDeadline, $updatedAssignment->getSecondDeadline()->getTimestamp());
    Assert::equal($maxPointsBeforeSecondDeadline, $updatedAssignment->getMaxPointsBeforeSecondDeadline());

    // check localized assignment
    Assert::count(1, $updatedAssignment->getLocalizedAssignments());
    $localized = current($localizedAssignments);
    $updatedLocalized = $updatedAssignment->getLocalizedAssignments()->first();
    Assert::equal($updatedLocalized->getLocale(), $localized["locale"]);
    Assert::equal($updatedLocalized->getDescription(), $localized["description"]);
    Assert::equal($updatedLocalized->getName(), $localized["name"]);
  }

  public function testCreateAssignment()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    /** @var Mockery\Mock | JobConfig\TestConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $baseTaskData = [
      'task-id' => 'anything',
      'priority' => 42,
      'fatal-failure' => false,
      'cmd' => ['bin' => 'echo'],
    ];

    $mockJobConfig->shouldReceive("getTests")->withAnyArgs()->andReturn([
      new JobConfig\TestConfig("test1", [
        new JobConfig\Tasks\ExternalTask($baseTaskData + [
          'type' => 'execution',
          'sandbox' => ['name' => 'isolate', 'limits' => []]
        ]),
        new JobConfig\Tasks\InternalTask($baseTaskData + [
          'type' => 'evaluation'
        ])
      ])
    ]);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig);
    $this->presenter->jobConfigs = $mockStorage;

    $mockUploadedStorage = Mockery::mock(\App\Helpers\UploadedJobConfigStorage::class);
    $mockUploadedStorage->shouldReceive("copyToUserAndUpdateRuntimeConfigs")->withAnyArgs()->andReturn();
    $this->presenter->uploadedJobConfigStorage = $mockUploadedStorage;

    $exercise = $this->presenter->exercises->findAll()[0];
    $group = $this->presenter->groups->findAll()[0];

    $request = new Nette\Application\Request(
      'V1:Assignments',
      'POST',
      ['action' => 'create'],
      ['exerciseId' => $exercise->id, 'groupId' => $group->id]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    // Make sure the assignment was persisted
    Assert::same($this->presenter->assignments->findOneBy(['id' => $result['payload']->id]), $result['payload']);
  }

  public function testRemove()
  {
    // TODO: not working, Integrity constraint violation: 19 FOREIGN KEY constraint failed
    // TODO: do we really need to delete assignments directly from database?

    /*$token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $assignment = current($this->assignments->findAll());

    $request = new Nette\Application\Request('V1:Assignments', 'DELETE',
      ['action' => 'remove', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);
    Assert::exception($this->assignments->findOrThrow($assignment->getId()), \App\Exceptions\NotFoundException::class);*/
  }

  public function testCanSubmit()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $assignment = current($this->assignments->findAll());

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'canSubmit', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(true, $result['payload']);
  }

  public function testSubmissions()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $submission = current($this->presenter->submissions->findAll());
    $user = $submission->getUser();
    $assignment = $submission->getAssignment();
    $submissions = $this->presenter->submissions->findSubmissions($assignment, $user->getId());

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'submissions', 'id' => $assignment->getId(), 'userId' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(count($submissions), $result['payload']);
    Assert::same($submissions, $result['payload']);
  }

  public function testSubmit()
  {
    // TODO:
  }

  public function testGetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $assignment = current($this->assignments->findAll());

    /** @var Mockery\Mock | JobConfig\TestConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $limits = [
      [
        'hardwareGroup' => 'group1',
        'tests' => []
      ]
    ];

    $mockJobConfig->shouldReceive("getHardwareGroups")->withAnyArgs()->andReturn(["group1", "group2"])->atLeast(1)
      ->shouldReceive("getLimits")->withAnyArgs()->andReturn($limits)->atLeast(1);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig);
    $this->presenter->jobConfigs = $mockStorage;

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'getLimits', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(1, $result['payload']);

    $environments = $result['payload']['environments'];
    Assert::count(1, $environments);

    $environment = current($environments);
    Assert::equal(["group1", "group2"], $environment['hardwareGroups']);
    Assert::equal($limits, $environment['limits']);
  }

  public function testSetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $assignment = current($this->assignments->findAll());
    $setLimitsCallCount = count($assignment->getSolutionRuntimeConfigs());

    // prepare limits arrays
    $limit1 = [
      'task1' => ['hw-group-id' => 'group1'],
      'task2' => ['hw-group-id' => 'group1']
    ];
    $limit2 = [
      'task1' => ['hw-group-id' => 'group2'],
      'task2' => ['hw-group-id' => 'group2']
    ];

    /** @var Mockery\Mock | JobConfig\TestConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("setLimits")->withArgs(['group1', $limit1])->andReturn()->times($setLimitsCallCount)
      ->shouldReceive("setLimits")->withArgs(['group2', $limit2])->andReturn()->times($setLimitsCallCount);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig)->times($setLimitsCallCount);
    $mockStorage->shouldReceive("saveJobConfig")->withAnyArgs()->andReturn()->times($setLimitsCallCount);
    $this->presenter->jobConfigs = $mockStorage;

    // construct post parameter environments
    $environments = [];
    foreach ($assignment->getSolutionRuntimeConfigs() as $runtimeConfig) {
      $environments[] = [
        'environment' => ['id' => $runtimeConfig->getId()],
        'limits' => [
          [
            'hardwareGroup' => 'group1',
            'tests' => ['testA' => $limit1]
          ],
          [
            'hardwareGroup' => 'group2',
            'tests' => ['testB' => $limit2]
          ]
        ]
      ];
    }

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'setLimits', 'id' => $assignment->getId()],
      ['environments' => $environments]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\ForwardResponse::class, $response);

    // result of setLimits is forward response which is set to getLimits action
    $req = $response->getRequest();
    Assert::equal(Nette\Application\Request::FORWARD, $req->getMethod());
  }
}

$testCase = new TestAssignmentsPresenter();
$testCase->run();