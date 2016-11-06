<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\InstancesPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;

/**
 * @httpCode any
 */
class TestInstancesPresenter extends Tester\TestCase
{
  private $adminLogin = "admin@admin.com";
  private $adminPassword = "admin";

  /** @var InstancesPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, InstancesPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetAllInstances()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Instances', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(1, count($result['payload']));
    $instance = array_pop($result['payload']);
    Assert::equal("Frankenstein University, Atlantida", $instance->name);
  }

  public function testCreateInstance()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Instances',
        'POST',
        ['action' => 'createInstance'],
        ['name' => 'NIOT', 'description' => 'Just a new instance', 'isOpen' => 'true']
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(201, $result['code']);
    $instance = $result['payload'];
    Assert::equal("NIOT", $instance->name);
    Assert::true($instance->isOpen);
    Assert::equal("Just a new instance", $instance->description);
  }

  public function testUpdateInstance()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allInstances = $this->presenter->instances->findAll();
    $instance = array_pop($allInstances);

    $request = new Nette\Application\Request('V1:Instances',
        'POST',
        ['action' => 'updateInstance', 'id' => $instance->id],
        ['name' => 'Frankenstein UNI', 'description' => 'Edited intence', 'isOpen' => 'true']
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $instance = $result['payload'];
    Assert::equal("Frankenstein UNI", $instance->name);
  }

  public function testDeleteInstance()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    // create new testing instance for further deletion
    $request = new Nette\Application\Request('V1:Instances',
        'POST',
        ['action' => 'createInstance'],
        ['name' => 'NIOT', 'description' => 'Just a new instance', 'isOpen' => 'true']
    );
    $response = $this->presenter->run($request);
    $newInstanceId = $response->getPayload()['payload']->id;

    $allInstances = $this->presenter->instances->findAll();
    Assert::equal(2, count($allInstances));

    $request = new Nette\Application\Request('V1:Instances',
        'DELETE',
        ['action' => 'deleteInstance', 'id' => $newInstanceId]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);
    Assert::equal(1, count($this->presenter->instances->findAll()));
  }

  public function testGetGroups()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allInstances = $this->presenter->instances->findAll();
    $instance = array_pop($allInstances);

    $request = new Nette\Application\Request('V1:Instances', 'GET', ['action' => 'groups', 'id' => $instance->id]);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $groups = $result['payload'];
    Assert::equal(1, count($groups));
    Assert::equal("Demo group", array_pop($groups)->name);
  }

  public function testGetUsers()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allInstances = $this->presenter->instances->findAll();
    $instance = array_pop($allInstances);

    $request = new Nette\Application\Request('V1:Instances', 'GET', ['action' => 'users', 'id' => $instance->id]);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->presenter->instances->get($instance->id)->getMembers(NULL)->getValues(), $result['payload']);
  }

  public function testGetLicences()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allInstances = $this->presenter->instances->findAll();
    $instance = array_pop($allInstances);

    $request = new Nette\Application\Request('V1:Instances', 'GET', ['action' => 'licences', 'id' => $instance->id]);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->presenter->instances->get($instance->id)->getLicences()->getValues(), $result['payload']);
  }

  public function testCreateLicences()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allInstances = $this->presenter->instances->findAll();
    $instance = array_pop($allInstances);

    $request = new Nette\Application\Request('V1:Instances',
        'POST',
        ['action' => 'createLicence', 'id' => $instance->id],
        ['note' => 'Another year', 'validUntil' => '2017-05-12 13:02:56']
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $licence = $result['payload'];
    Assert::equal($instance->id, $licence->instance->id);
    Assert::equal('Another year', $licence->note);
    Assert::equal(new DateTime('2017-05-12 13:02:56'), $licence->validUntil);
  }

  public function testUpdateLicences()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Instances',
        'POST',
        ['action' => 'updateLicence', 'id' => ''],
        ['note' => 'Changed description', 'validUntil' => '2020-01-01 13:02:56', 'isValid' => 'false']
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $licence = $result['payload'];
    Assert::equal('Changed description', $licence->note);
    Assert::equal(new DateTime('2020-01-01 13:02:56'), $licence->validUntil);
    Assert::false($licence->isValid);
  }

  public function testRemoveLicences()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Instances',
        'DELETE',
        ['action' => 'deleteLicence', 'id' => '']
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);
  }
}

$testCase = new TestInstancesPresenter();
$testCase->run();