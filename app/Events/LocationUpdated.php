<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $driverId;
    public $locationData;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct($driverId, $locationData)
    {
        $this->driverId = $driverId;
        $this->locationData = $locationData;
        $this->timestamp = now();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('driver-locations'),
            new Channel("driver.{$this->driverId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'latitude' => $this->locationData['latitude'],
            'longitude' => $this->locationData['longitude'],
            'accuracy' => $this->locationData['accuracy'] ?? 0,
            'bearing' => $this->locationData['bearing'] ?? 0,
            'speed' => $this->locationData['speed'] ?? 0,
            'is_online' => $this->locationData['is_online'] ?? false,
            'status' => $this->locationData['status'] ?? 0,
            'timestamp' => $this->timestamp->toISOString()
        ];
    }
} 