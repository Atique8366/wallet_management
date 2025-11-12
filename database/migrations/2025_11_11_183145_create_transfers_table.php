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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_wallet_id');
            $table->unsignedBigInteger('target_wallet_id');
            $table->bigInteger('amount'); // minor units
            $table->string('idempotency_key')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('source_wallet_id')->references('id')->on('wallets');
            $table->foreign('target_wallet_id')->references('id')->on('wallets');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfers');
    }
};
