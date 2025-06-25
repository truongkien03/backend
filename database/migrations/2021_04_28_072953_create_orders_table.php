<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->json('from_address');
            $table->json('to_address');
            $table->json('items');
            $table->float('shipping_cost', 19, 2);
            $table->float('distance', 8, 2);
            $table->float('discount')->nullable();
            $table->integer('status_code')->default(config('const.order.status.pending'));
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('driver_accept_at')->nullable();
            $table->text('user_note')->nullable();
            $table->text('driver_note')->nullable();
            $table->integer('driver_rate')->nullable();
            $table->integer('is_sharable')->nullable()->default(0);
            $table->json('except_drivers')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
