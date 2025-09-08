<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpMqtt\Client\MqttClient;
use Illuminate\Support\Facades\Redis;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    private $server = 'mqtt.boothlab.id';
    private $port   = 1883;

    /**
     * Create a fresh MQTT client for each request
     */
    private function createMqttClient()
    {
        $clientId = uniqid('lumen-client-');
        $mqtt = new MqttClient($this->server, $this->port, $clientId);

        try {
            $mqtt->connect();
        } catch (MqttClientException $e) {
            throw new \Exception('MQTT connection failed: ' . $e->getMessage());
        }

        return $mqtt;
    }

    // 🔹 Get ESP status
    private static $pendingResponses = []; // in-memory storage

    public function getStatus($activationCode)
    {
        $requestId = uniqid();
        self::$pendingResponses[$requestId] = null;

        // Publish MQTT request
        $mqtt->publish("socket/$activationCode/get_status", json_encode([
            'request_id' => $requestId
        ]));

        $start = time();
        $timeout = 3; // 3 seconds
        while ((time() - $start) < $timeout) {
            if (self::$pendingResponses[$requestId]) {
                $status = self::$pendingResponses[$requestId];
                unset(self::$pendingResponses[$requestId]);
                return response()->json($status);
            }
            usleep(100_000); // sleep 0.1s to reduce CPU usage
        }

        unset(self::$pendingResponses[$requestId]);
        return response()->json(['error' => 'ESP did not respond in time'], 504);
    }

    public function callback(Request $request)
    {
        $requestId = $request->input('request_id');
        $status = $request->input('status');

        if (isset(self::$pendingResponses[$requestId])) {
            self::$pendingResponses[$requestId] = $status;
            return response()->json(['ok' => true]);
        }

        return response()->json(['error' => 'Unknown request_id'], 400);
    }

    // 🔹 Turn LED on
    public function turnOn(Request $request)
    {
        return $this->publishMessage('on', 'LED turned on', $request->activation_code);
    }

    // 🔹 Turn LED off
    public function turnOff(Request $request)
    {
        return $this->publishMessage('off', 'LED turned off', $request->activation_code);
    }

    // 🔹 Schedule Socket
    public function schedule(Request $request)
    {
        $scheduleJson = $request->getContent();

        return $this->publishMessage(
            $scheduleJson,
            'Schedule updated',
            $request->activation_code,
            true
        );
    }

    // 🔹 Trigger WiFi config portal on ESP
    public function startWifiConfig(Request $request)
    {
        return $this->publishMessage('wificonfig', 'WiFi config portal triggered', $request->activation_code);
    }

    // 🔹 Helper method for publishing messages
    private function publishMessage($message, $successText, $activation_code, $isSchedule = false)
    {
        $topic = $isSchedule
            ? "socket/$activation_code/schedule"
            : "socket/$activation_code";

        try {
            $mqtt = $this->createMqttClient();
            $mqtt->publish($topic, $message, 0);
            $mqtt->disconnect();
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'error' => $e->getMessage()]);
        }

        return response()->json(['status' => $successText]);
    }
}