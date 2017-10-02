<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\SendAssignmentDeadlineNotification;
use App\Helpers\EmailHelper;
use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Repository\Assignments;
use App\Model\Repository\Exercises;
use App\Model\Repository\Groups;
use App\Model\Repository\Users;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Tester\Assert;

/**
 * @testCase
 */
class TestDeadlineNotifications extends Tester\TestCase
{
  /** @var Assignments */
  private $assignments;

  /** @var SendAssignmentDeadlineNotification */
  protected $command;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var Nette\DI\Container */
  private $container;

  /** @var Users */
  private $users;

  /** @var Mockery\Mock|EmailHelper */
  private $emailHelperMock;

  /** @var AssignmentEmailsSender */
  private $sender;

  /** @var Exercise */
  private $demoExercise;

  /** @var Group */
  private $demoGroup;

  public function __construct(Nette\DI\Container $container) {
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->users = $this->container->getByType(Users::class);
  }

  protected function setUp() {
    PresenterTestHelper::fillDatabase($this->container);
    $this->demoExercise = $this->container->getByType(Exercises::class)->findAll()[0];

    /** @var Group $group */
    foreach ($this->container->getByType(Groups::class)->findAll() as $group) {
      if ($group->getStudents()->count() > 0) {
        $this->demoGroup = $group;
      }
    }

    $this->emailHelperMock = Mockery::mock(EmailHelper::class);
    $this->sender = new AssignmentEmailsSender($this->emailHelperMock, []);
    $this->assignments = $this->container->getByType(Assignments::class);
    $this->command = new SendAssignmentDeadlineNotification(
      "1 day",
      $this->assignments,
      $this->sender
    );
  }

  protected function tearDown() {
    Mockery::close();
  }

  public function testNothing() {
    $assignment = Assignment::assignToGroup($this->demoExercise, $this->demoGroup, TRUE);

    $deadline = new DateTime();
    $deadline->modify("+3 days");
    $assignment->setFirstDeadline($deadline);
    $this->assignments->persist($assignment);

    $this->emailHelperMock->shouldNotReceive("send");
    $input = new StringInput("'1 day'");
    $this->command->run($input, new NullOutput());

    Assert::true(TRUE); // We make no assertions here - all the work is done by Mockery
  }

  public function testFirstDeadlineNearby() {
    $assignment = Assignment::assignToGroup($this->demoExercise, $this->demoGroup, TRUE);

    $deadline = new DateTime();
    $deadline->modify("+36 hours");
    $assignment->setFirstDeadline($deadline);
    $this->assignments->persist($assignment);

    $this->emailHelperMock->shouldReceive("send")->once()->andReturn(TRUE);
    $input = new StringInput("'1 day'");
    $this->command->run($input, new NullOutput());

    Assert::true(TRUE); // We make no assertions here - all the work is done by Mockery
  }
}

$testCase = new TestDeadlineNotifications($container);
$testCase->run();