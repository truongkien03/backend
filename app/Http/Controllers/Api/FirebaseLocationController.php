<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseRealtimeService;
use Illuminate\Http\Request;

class FirebaseLocationController extends Controller
{
    private $firebaseService;

    public function __construct()
    {
        $this->firebaseService = new FirebaseRealtimeService();
    }

    /**
     * Lấy tọa độ hiện tại của driver
     */
    public function getDriverLocation(Request $request, $driverId)
    {
        $location = $this->firebaseService->getDriverLocation($driverId);
        
        if ($location) {
            return response()->json([
                'success' => true,
                'data' => $location
            ]);
        }

        return response()->json([
            'error' => true,
            'message' => 'Driver location not found'
        ], 404);
    }

    /**
     * Lấy tất cả driver đang online
     */
    public function getOnlineDrivers()
    {
        $drivers = $this->firebaseService->getAllOnlineDrivers();
        
        return response()->json([
            'success' => true,
            'data' => $drivers
        ]);
    }

    /**
     * Test kết nối Firebase
     */
    public function testConnection()
    {
        try {
            $drivers = $this->firebaseService->getAllOnlineDrivers();
            
            return response()->json([
                'success' => true,
                'message' => 'Firebase connection successful',
                'online_drivers_count' => count($drivers)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Firebase connection failed: ' . $e->getMessage()
            ], 500);
        }
    }
} 