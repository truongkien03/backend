<?php

use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    // Optimized simple function - minimal processing
    Console::log('Function started');
    
    $payload = $request['payload'] ?? '{}';
    $data = json_decode($payload, true) ?: [];
    
    // Simple response without complex processing
    $result = [
        'success' => true,
        'message' => 'Function executed successfully',
        'received_data' => $data,
        'timestamp' => time(),
        'function_id' => '686a1e4a0010de76b3ea'
    ];
    
    Console::log('Function completed');
    return $result;
    
}, ['utopia', 'request', 'response', 'args']);

App::shutdown(function (array $utopia, array $request, array $response, array $args) {
    Console::log('Function shutdown');
}, ['utopia', 'request', 'response', 'args']); 