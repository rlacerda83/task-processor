<?php

require_once __DIR__.'/../vendor/autoload.php';

use App\Controller\TaskController;

$app = new Silex\Application();

$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__."/../config/local.json"));
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app->after(function (\Symfony\Component\HttpFoundation\Request $req, \Symfony\Component\HttpFoundation\Response $res) {
    if ($req->get('callback') !== null && $req->getMethod() === 'GET') {
        $contentType = $res->headers->get('Content-Type');
        $jsonpContentTypes = array(
            'application/json',
            'application/json; charset=utf-8',
            'application/javascript',
        );

        if (!in_array($contentType, $jsonpContentTypes)) {
            return;
        }

        if ($res instanceof \Symfony\Component\HttpFoundation\JsonResponse) {
            $res->setCallback($req->get('callback'));
        } else {
            $res->setContent($req->get('callback')
                . '(' . $res->getContent() . ');'
            );
        }
    }
});

$app['task.controller'] = $app->share(function() use ($app) {
    return new TaskController($app);
});

$app->get('/processor', "task.controller:index");
$app->run();
// end