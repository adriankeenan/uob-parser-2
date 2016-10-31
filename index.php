<?php

error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

// Handle subdir
$base = dirname($_SERVER['PHP_SELF']);

if (ltrim($base, '/'))
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($base));

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$container = new \Slim\Container();

$container['notFoundHandler'] = function ($container) {
    return function (Request $request, Response $response) use ($container) {
        
        $data = [
            'error' => true,
            'error_str' => 'You appear to be lost, this route doesn\'t exist',
            'routes' => [
                '/courses' => 'Get a list of courses and departments',
                '/sessions' => 'Get a list of sessions for a given course'
            ]
        ];

        return $container['response']->withJson($data);
    };
};

$app = new \Slim\App($container);

$app->get('/courses', function (Request $request, Response $response) {
    
    $parser = new UoBParser\Parser();
    $courses = $parser->getCourses();

    return $response->withJson($courses);
});

$app->get('/sessions', function (Request $request, Response $response) {
    
    $dept   = $request->getParam('dept');
    $course = $request->getParam('course');
    $level  = $request->getParam('level');

    $parser = new UoBParser\Parser();
    $sessions = $parser->getSessions($dept, $course, $level);

    return $response->withJson($sessions);
});

$app->run();