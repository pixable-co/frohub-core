<?php

// Ensure this script runs only in CLI mode
if (PHP_SAPI !== 'cli') {
    exit('This script can only be run in CLI mode.');
}

require __DIR__ . '/vendor/autoload.php'; // Load Composer autoloader

use Symfony\Component\Console\Application;
use Pixable\FrohubCore\MakeShortcodeCommand;
use Pixable\FrohubCore\MakeShortcodeReactCommand;
use Pixable\FrohubCore\MakeApiCommand;


use Pixable\FrohubCore\DeleteShortcodeCommand;
use Pixable\FrohubCore\DeleteShortcodeReactCommand;
use Pixable\FrohubCore\DeleteApiCommand;

// Create a new Symfony Console Application
$application = new Application();

// Register your custom commands
$application->add(new MakeShortcodeCommand());
$application->add(new MakeShortcodeReactCommand());
$application->add(new MakeApiCommand());

// Delete Commands
$application->add(new DeleteShortcodeCommand());
$application->add(new DeleteShortcodeReactCommand());
$application->add(new DeleteApiCommand());

// Run the application
$application->run();
