<?php

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();

$appDir = __DIR__ . '/../app';

$configurator = new Nette\Configurator;
$configurator->setDebugMode(FALSE);
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->addParameters(['appDir' => $appDir]);

$configurator->createRobotLoader()
	->addDirectory($appDir)
  ->addDirectory(__DIR__ . '/base')
	->register();

$configurator->addConfig(__DIR__ . '/../app/config/config.neon');
$configurator->addConfig(__DIR__ . '/config.tests.neon');

return $configurator->createContainer();
