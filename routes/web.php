<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->post('/register', 'AuthController@register');
$router->post('/login', 'AuthController@login');

$router->group(['middleware' => 'authToken'], function () use ($router) {
  $router->get('/led/on', 'Controller@turnOn');
  $router->get('/led/off', 'Controller@turnOff');
  $router->get('/config_wifi', 'Controller@startWifiConfig');
  $router->post('/logout', 'AuthController@logout');
});