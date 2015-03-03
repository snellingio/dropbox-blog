<?php

/**
 * This file is used to bootstrap the Slim application:
 * - set templates path
 * - configure blog posts folder
 * - set log writer settings
 * - enable twig view
 * - add a 404 error handler
 * - set default variables for the views
 **/

session_start();

/**
 * set the production mode if the env.remote file exists
 */
$mode = 'development';
if (file_exists(__DIR__.'/env.remote')) {
   $mode = 'production';
}

/**
 * Initialize the Slim application
 */
$app = new \Slim\Slim(array(
    'app.name' => 'Dropbox Blog',
    'debug' => true,
    'templates.path' => realpath(__DIR__ . '/../templates'),
    'log.level' => \Slim\Log::DEBUG,
    'log.enabled' => true,
    'log.writer' => new \Slim\Extras\Log\DateTimeFileWriter(array(
        'path' => realpath(__DIR__ . '/../logs'),
        'name_format' => 'y-m-d'
    )),
    'cache' => realpath(__DIR__ . '/../cache/'),
    'blogs.path' => realpath(__DIR__ . '/../cache/blogs/'),
    'dropbox.settings' => array(
       'key' => 'hrs911610nfmgk2',
       'secret' => 'hpgxrfah406j11k',
       'encrypter' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'
    ),
    'database.settings' => array(
       'username' => 'root',
       'password' => 'root',
       'database' => 'dropbox',
       'host' => 'localhost',
       'port' => '8889'
    ),
    'mode' => $mode
));

/**
 * condifure settings for production environment
 */
$app->configureMode('production', function () use ($app) {
   $app->config('debug', false);
   $app->config('database.settings', array(
       'username' => '',
       'password' => '',
       'database' => '',
       'host' => 'localhost',
       'port' => '3306'
    ));
    $app->config('dropbox.settings', array(
       'key' => '5in7ria0gvoxnzg',
       'secret' => 'xztcjqfhsfwgmqd',
       'encrypter' => '2bd73145238c6f3f0f18117af88f1094'
    ));
});

/**
 * Prepare the Twig view with the Slim Extras
 */
\Slim\Extras\Views\Twig::$twigOptions = array(
    'charset' => 'utf-8',
    'cache' => realpath(__DIR__ . '/../cache/templates'),
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);
$app->view(new \Slim\Extras\Views\Twig());

/**
 * Instantiate the blog engine and add it to the Slim config
 */
$app->config('blogEngine', new \FileBlogEngine($app->config('blogs.path')));

/**
 * Instantiate the BlogDB class and add it to the Slim config
 */
$app->config('blogDB', new \Blog($app->config('database.settings')));

/**
 * Instantiate DropboxStorage and add it to the Slim config
 */
$app->config('dropboxStorage', new DropboxStorage(
    $app->config('blogs.path'),
    $app->config('database.settings'),
    $app->config('dropbox.settings')));


/**
 * Add the 404 page not found error handler
 */
$app->notfound(function() use ($app) {
    $app->render('404.html', array('title'=>'404'));
});

/**
 * Add some predefined variables
 */
$app->hook('slim.before', function() use ($app) {
    $hostName = ($app->request()->getPort() == 80)? $app->request()->getHost() : $app->request()->getHostWithPort();
    $app->view()->appendData(array(
        'blogIndex' => $app->urlFor('blogIndex'),
        'feed' => $app->urlFor('feed'),
        'hostName' => $hostName,
        'appName' => $app->config('app.name')
    ));
});

