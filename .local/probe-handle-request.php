<?php
require __DIR__.'/../vendor/autoload.php';
use Illuminate\Http\Request;
$app = require __DIR__.'/../bootstrap/app.php';
ob_start();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/login';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8004';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8004';
$app->handleRequest(Request::capture());
file_put_contents(__DIR__.'/handle-request.out', ob_get_clean());
echo 'done';
