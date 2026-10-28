<?php

use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

/** The one-column table the sent-message test's notifiable lives in. */
return new class extends Migration
{
    public function up(): void
    {
        app('db')->connection()->getSchemaBuilder()->create('sent_message_users', function (Blueprint $table) {
            $table->increments('id');
        });
    }

    public function down(): void
    {
        app('db')->connection()->getSchemaBuilder()->dropIfExists('sent_message_users');
    }
};
