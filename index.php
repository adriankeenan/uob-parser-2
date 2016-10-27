<?php

error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

//handle subdir
$base = dirname($_SERVER['PHP_SELF']);

if (ltrim($base, '/'))
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($base));

$klein = new \Klein\Klein();

$klein->respond('GET', '/courses', function($request, $response){

    $parser = new UoBParser\Parser();
    $response->json($parser->getCourses());
});

$klein->respond('GET', '/sessions', function($request, $response){

    $dept   = $request->param('dept');
    $course = $request->param('course');
    $level  = $request->param('level');

    $parser = new UoBParser\Parser();
    $response->json($parser->getSessions($dept, $course, $level));
});

$klein->onHttpError(function($code, $router){
      
    $data = [
        'error' => true,
        'error_str' => 'You appear to be lost, this route doesn\'t exist',
        'routes' => [
            'courses' => 'Get a list of courses and departments',
            'sessions' => 'Get a list of sessions for a given course'
        ]
    ];

    $router->response()->json($data);
});

$klein->dispatch();