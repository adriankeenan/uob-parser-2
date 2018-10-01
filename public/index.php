<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

DEFINE('UOB_PARSER_VERSION', 2);

/**
 * Build an array containg data from an exception.
 * @param Exception|null $exception
 * @return array
 */
function exceptionToArray($exception){

    if ($exception == null)
        return null;

    return [
        'class' => get_class($exception),
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'id' => $exception instanceof \UoBParser\Error ? $exception->getID() : null,
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'previous' => exceptionToArray($exception->getPrevious()),
    ];
}

// Get debug
$debug = getenv('UOB_PARSER_DEBUG') === '1';

// Init slim
$container = new \Slim\Container();

$container['notFoundHandler'] = function($container) {

    return function (Request $request, Response $response) use ($container) {

        $data = [
            'error' => true,
            'error_str' => 'You appear to be lost, this route doesn\'t exist',
            'routes' => [
                '/courses' => 'Get a list of courses and departments',
                '/sessions' => 'Get a list of sessions for a given course'
            ],
            'api_version' => [
                'requested_version' => $request->getAttribute('apiVersion'),
                'latest_version' => UOB_PARSER_VERSION,
                'default_version' => 1,
                'notes' => 'Specify API version with "api_version" query parameter or "API-Version" header'
            ],
        ];

        return $container['response']->withJson($data, 404, JSON_PRETTY_PRINT);
    };
};

$container['errorHandler'] = function($container) {

    return function(Request $request, Response $response, Exception $exception) use ($container) {

        $data = [
            'error' => true,
            'error_str' => $exception->getMessage(),
            'error_id' => $exception instanceof \UoBParser\Error ? $exception->getID() : null,
        ];

        // If debug enabled, add exception data
        if ($container->config['debug'])
            $data['exception'] = exceptionToArray($exception);

        // Set code based on exception type
        $code = 500;
        if ($exception instanceof InvalidArgumentException)
            $code = 422;

        return $container['response']->withJson($data, $code, JSON_PRETTY_PRINT);
    };
};

$container['config'] = ['debug' => $debug];

$app = new \Slim\App($container);

$app->add(function(Request $request, Response $response, $next) {

    // Get API version from Header or params
    $headerVersion = $request->getHeaderLine('API-Version');
    $paramVersion = $request->getParam('api_version');

    // Get first specified version
    $version = 1;
    $versions = array_values(array_filter([$headerVersion, $paramVersion]));
    if (count($versions) > 0)
        $version = $versions[0];

    // Validate
    if (filter_var($version, FILTER_VALIDATE_INT) === false || $version < 1 || $version > UOB_PARSER_VERSION){
        $msg = 'Invalid version: '.$version.', must be integer between 1 and '.UOB_PARSER_VERSION;
        throw new InvalidArgumentException($msg, 422);
    }

    // Set as attribute, so we can fetch it in the route handler
    $request = $request->withAttribute('apiVersion', intval($version));

    $response = $next($request, $response);

    // Add as response header
    $response = $response->withHeader('API-Version', $version);

    return $response;
});

$app->get('/courses', function(Request $request, Response $response) {

    $version = $request->getAttribute('apiVersion');

    $parser = new UoBParser\Parser($version);
    $courses = $parser->getCourses();

    return $response->withJson($courses, 200, JSON_PRETTY_PRINT);
});

$app->get('/sessions', function(Request $request, Response $response) {

    $dept   = $request->getParam('dept');
    $course = $request->getParam('course');
    $level  = $request->getParam('level');

    $version = $request->getAttribute('apiVersion');

    $parser = new UoBParser\Parser($version);
    $sessions = $parser->getSessions($dept, $course, $level);

    return $response->withJson($sessions, 200, JSON_PRETTY_PRINT);
});

$app->run();