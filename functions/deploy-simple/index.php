<?php

use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    // Simple function that just returns success
    Console::log('Function started successfully');
    
    $payload = $request['payload'] ?? '{}';
    Console::log('Received payload: ' . $payload);
    
    // Simple response
    $result = [
        'success' => true,
        'message' => 'Function executed successfully',
        'timestamp' => time(),
        'function_id' => '686a1e4a0010de76b3ea'
    ];
    
    Console::log('Function completed successfully');
    return $result;
    
}, ['utopia', 'request', 'response', 'args']);

App::shutdown(function (array $utopia, array $request, array $response, array $args) {
    Console::log('Function shutdown completed');
}, ['utopia', 'request', 'response', 'args']); 