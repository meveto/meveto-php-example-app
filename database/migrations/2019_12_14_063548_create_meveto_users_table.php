<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMevetoUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meveto_users', function (Blueprint $table) {
            $table->bigInteger('id')->unsigned()->unique();
            $table->foreign('id')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('last_logged_in')->unsigned()->nullable()->default(null);
            $table->bigInteger('last_logged_out')->unsigned()->nullable()->default(null);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meveto_users');
    }
}
