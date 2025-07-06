<?php

use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    /* 
     * Simple Test Function
     * 
     * Function đơn giản để test kết nối Appwrite
     */
    
    Console::log('Simple test function started');
    
    $payload = $request['payload'] ?? '{}';
    $data = json_decode($payload, true) ?: [];
    
    Console::log('Received payload: ' . $payload);
    
    // Simple response
    $result = [
        'success' => true,
        'message' => 'Simple test function executed successfully',
        'received_data' => $data,
        'timestamp' => time(),
        'function_id' => '686a1e4a0010de76b3ea',
        'test' => true
    ];
    
    Console::log('Function completed successfully');
    
    return $result;
    
}, ['utopia', 'request', 'response', 'args']);

App::shutdown(function (array $utopia, array $request, array $response, array $args) {
    Console::log('Simple test function shutdown');
}, ['utopia', 'request', 'response', 'args']); 