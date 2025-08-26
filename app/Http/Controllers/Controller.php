<?php

namespace App\Http\Controllers;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    private $server = 'mqtt.boothlab.id';
    private $port   = 1883;
    private $clientId;
    private $mqtt;

    public function __construct()
    {
        $this->clientId = uniqid('lumen-client-');
        $this->mqtt = new MqttClient($this->server, $this->port, $this->clientId);

        try {
            $this->mqtt->connect();
        } catch (MqttClientException $e) {
            error_log('MQTT connection failed: ' . $e->getMessage());
        }
    }

    // Turn LED on
    public function turnOn()
    {
        return $this->publishMessage('on', 'LED turned on');
    }

    // Turn LED off
    public function turnOff()
    {
        return $this->publishMessage('off', 'LED turned off');
    }

    // Trigger WiFi config portal on ESP
    public function startWifiConfig()
    {
        return $this->publishMessage('wifi_config', 'WiFi config portal triggered');
    }

    // Helper method
    private function publishMessage($message, $successText)
    {
        try {
            $this->mqtt->publish('home/led/control', $message, 0);
        } catch (MqttClientException $e) {
            return response()->json(['status' => 'Failed', 'error' => $e->getMessage()]);
        }

        return response()->json(['status' => $successText]);
    }

    public function __destruct()
    {
        if ($this->mqtt->isConnected()) {
            $this->mqtt->disconnect();
        }
    }
}