<?php

/**
 * Require autoload and load the bootstrap and routes files before running the application
 */

require __DIR__ . '/../vendor/autoload.php';
require 'bootstrap.php';
require 'routes.php';

$app->run();
