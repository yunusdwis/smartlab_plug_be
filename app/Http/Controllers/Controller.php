<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpMqtt\Client\MqttClient;
// use Illuminate\Support\Facades\Storage;
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

    //get esp status
    public function getStatus(Request $request)
    {
        $activationCode = $request->activation_code;
        $topic = "socket/$activationCode/status";
        $response = null;

        try {
            $this->mqtt->subscribe($topic, function (string $topic, string $message) use (&$response) {
                $response = json_decode($message, true);
            });

            // Ask ESP for status
            $this->mqtt->publish("socket/$activationCode/get_status", "get_status");

            // Wait a bit for ESP to respond (e.g. max 2s)
            $timeout = microtime(true) + 2;
            while ($response === null && microtime(true) < $timeout) {
                $this->mqtt->loop(true);
            }

            if ($response === null) {
                return response()->json(['error' => 'No response from device'], 504);
            }

            return response()->json($response);

        } catch (MqttClientException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Turn LED on
    public function turnOn(Request $request)
    {
        return $this->publishMessage('on', 'LED turned on', $request->activation_code);
    }

    // Turn LED off
    public function turnOff(Request $request)
    {
        return $this->publishMessage('off', 'LED turned off', $request->activation_code);
    }

    // Schedule Socket
    public function schedule(Request $request){
        // Get the whole JSON body as string
        $scheduleJson = $request->getContent(); 

        return $this->publishMessage(
            $scheduleJson,
            'Schedule updated',
            $request->activation_code,
            true // flag as schedule
        );
    }

    // Trigger WiFi config portal on ESP
    public function startWifiConfig(Request $request)
    {
        return $this->publishMessage('wificonfig', 'WiFi config portal triggered', $request->activation_code);
    }

    // Helper method
    private function publishMessage($message, $successText, $activation_code, $isSchedule = false){
        $topic = $isSchedule 
            ? "socket/$activation_code/schedule"
            : "socket/$activation_code";

        try {
            $this->mqtt->publish($topic, $message, 0);
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