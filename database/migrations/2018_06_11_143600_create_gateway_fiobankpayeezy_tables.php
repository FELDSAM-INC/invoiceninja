<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGatewayFiobankpayeezyTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gateway_fiobankpayeeze_batches', function(Blueprint $table)
        {
            $table->increments('id');
            $table->text('result');
            $table->integer('result_code');
            $table->integer('count_reversal')->nullable();
            $table->integer('count_transaction')->nullable();
            $table->integer('amount_reversal')->nullable();
            $table->integer('amount_transaction')->nullable();
            $table->text('response');
            $table->timestamp('closed_at')->nullable();
        });

        Schema::create('gateway_fiobankpayeeze_errors', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('action', 20);
            $table->text('response');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('gateway_fiobankpayeeze_transactions', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('invoice_number');
            $table->string('trans_id', 50);
            $table->integer('amount');
            $table->integer('currency');
            $table->string('client_ip_addr', 50);
            $table->text('description');
            $table->string('language', 50);
            $table->string('dms_ok', 50)->nullable();
            $table->string('result', 50)->nullable();
            $table->string('result_code', 50)->nullable();
            $table->string('result_3dsecure', 50)->nullable();
            $table->string('card_number', 50)->nullable();
            $table->text('response');
            $table->integer('reversal_amount')->default(0);
            $table->integer('makeDMS_amount')->default(0);
            $table->timestamps();

            $table->index('trans_id', 'trans_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gateway_fiobankpayeeze_batches');
        Schema::dropIfExists('gateway_fiobankpayeeze_errors');
        Schema::dropIfExists('gateway_fiobankpayeeze_transactions');
    }
}
