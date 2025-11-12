<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key');
            $table->string('resource_type'); // deposit, withdraw, transfer
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['idempotency_key','resource_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
