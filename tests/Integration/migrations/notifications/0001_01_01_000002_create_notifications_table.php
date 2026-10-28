<?php

use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

/** The notifications table for the database channel: Testbench's "notifications" migration set. */
return new class extends Migration
{
    public function up(): void
    {
        app('db')->connection()->getSchemaBuilder()->create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        app('db')->connection()->getSchemaBuilder()->dropIfExists('notifications');
    }
};
