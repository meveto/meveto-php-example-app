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
            $table->bigIncrements('id');
            $table->string('user_identifier')->unique();
            $table->timestamp('last_logged_in')->nullable()->default(null);
            $table->timestamp('last_logged_out')->nullable()->default(null);
            $table->boolean('is_logged_in')->default(false);
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
