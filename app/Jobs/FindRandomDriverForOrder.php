<?php

namespace App\Jobs;

use App\Models\Driver;
use App\Notifications\NoAvailableDriver;
use App\Notifications\WaitForDriverConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FindRandomDriverForOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $driver = $this->randomDriver();

        if (is_null($driver)) {
            $this->order->customer->notify(new NoAvailableDriver($this->order));
            return;
        }

        $driver->notify(new WaitForDriverConfirmation($this->order));
    }

    private function randomDriver()
    {
        $place = $this->order->from_address;

        $lat2 = $place['lat'];
        $lng2 = $place['lon'];

        $driver = Driver::has('profile')
            ->selectRaw(
                "*,
            6371 * acos(
                cos( radians($lat2) )
              * cos( radians( JSON_EXTRACT(current_location, '$.lat') ) )
              * cos( radians( JSON_EXTRACT(current_location, '$.lon') ) - radians($lng2) )
              + sin( radians($lat2) )
              * sin( radians( JSON_EXTRACT(current_location, '$.lat') ) )
                ) as distance"
            )
            ->where('status', config('const.driver.status.free'))
            ->whereNotIn('id', $this->order->except_drivers ?? [])
            ->orderBy('distance')
            ->orderBy('review_rate', 'desc')
            ->first();

        return $driver;
    }
}
