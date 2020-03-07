<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRealvalidationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gf_realvalidation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('hash', 40)->index();
            $table->string('status', 100);
            $table->boolean('is_valid')->default(0);
            $table->boolean('is_cell')->default(0);
            $table->string('caller_name', 100)->nullable();
            $table->string('carrier', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->default(\DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
            // $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('realvalidation');
    }
}
