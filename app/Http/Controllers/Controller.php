<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use PhpMqtt\Client\MqttClient;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
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

    public function getStatus(Request $request){
        $data = Device::where('activation_code', $request->activation_code)->first();
        if(!$data){
            return response()->json([
                'message' => 'activation code is wrong'
            ], 404);
        }
        return response()->json($data);
    }

    public function updateStatus(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'activation_code' => 'required|string',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $device = Device::where('activation_code', $request->activation_code)->first();

        if (!$device) {
            $device = Device::create([
                'activation_code' => $request->activation_code,
                'status' => $request->status,
                'schedule' => $request->schedule ?? [],
            ]);
        } else {
            $device->status = $request->status;

            if ($request->has('schedule')) {
                $device->schedule = $request->schedule; 
            }

            $device->save();
        }

        return response()->json([
            'message' => 'Device saved successfully',
            'device' => $device,
        ]);
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