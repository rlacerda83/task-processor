<?php

require_once __DIR__.'/../vendor/autoload.php';

use App\Controller\TaskController;

$app = new Silex\Application();

$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__."/../config/local.json"));
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app['task.controller'] = $app->share(function() use ($app) {
    return new TaskController($app);
});

$app->get('/processor', "task.controller:index");

$app->run();