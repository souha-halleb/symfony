<?php
$data = json_encode(['email' => 'unique_' . uniqid() . '@example.com', 'password' => 'secret123']);
$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $data,
        'ignore_errors' => true,
    ],
];
$context = stream_context_create($opts);
$response = @file_get_contents('http://nginx/api/auth/register', false, $context);
var_dump($response);
var_dump($http_response_header);
