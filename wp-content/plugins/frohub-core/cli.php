<?php

// Ensure this script runs only in CLI mode
if (PHP_SAPI !== 'cli') {
    exit('This script can only be run in CLI mode.');
}

require __DIR__ . '/vendor/autoload.php'; // Load Composer autoloader

use Symfony\Component\Console\Application;
use Pixable\FrohubCore\MakeShortcodeCommand;
use Pixable\FrohubCore\MakeApiCommand;

// Create a new Symfony Console Application
$application = new Application();

// Register your custom commands
$application->add(new MakeShortcodeCommand());
$application->add(new MakeApiCommand());

// Run the application
$application->run();
