<?php

use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

/** The bare users table the notification tests write to: an address and an optional name, as their Schema::create() built it. */
return new class extends Migration
{
    public function up(): void
    {
        app('db')->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->string('name')->nullable();
        });
    }

    public function down(): void
    {
        app('db')->connection()->getSchemaBuilder()->dropIfExists('users');
    }
};
