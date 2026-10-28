<?php

use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

/** The sessions table for the database session driver: Testbench's "session" migration set. */
return new class extends Migration
{
    public function up(): void
    {
        app('db')->connection()->getSchemaBuilder()->create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        app('db')->connection()->getSchemaBuilder()->dropIfExists('sessions');
    }
};
