<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->post('/register', 'AuthController@register');
$router->post('/login', 'AuthController@login');

$router->group(['middleware' => 'authToken'], function () use ($router) {
  $router->post('/socket/on', 'Controller@turnOn');
  $router->post('/socket/off', 'Controller@turnOff');
  $router->post('/socket/schedule', 'Controller@schedule');
  $router->post('/config_wifi', 'Controller@startWifiConfig');
  $router->post('/logout', 'AuthController@logout');
});