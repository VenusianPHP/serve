<?php

use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

/** The users table an app starts with: what Testbench's #[WithMigration] (the "laravel" set) gives a test. */
return new class extends Migration
{
    public function up(): void
    {
        $schema = app('db')->connection()->getSchemaBuilder();

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        $schema = app('db')->connection()->getSchemaBuilder();

        $schema->dropIfExists('password_reset_tokens');
        $schema->dropIfExists('users');
    }
};
