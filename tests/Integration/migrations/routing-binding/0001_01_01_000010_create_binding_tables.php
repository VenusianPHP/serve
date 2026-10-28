<?php

use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

/** The tables the implicit model route binding tests bind from: users (soft deleting), posts, tags (ulid) and comments (uuid). */
return new class extends Migration
{
    public function up(): void
    {
        $schema = app('db')->connection()->getSchemaBuilder();

        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->timestamps();
        });

        $schema->create('tags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug');
            $table->integer('post_id');
            $table->timestamps();
        });

        $schema->create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug');
            $table->integer('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $schema = app('db')->connection()->getSchemaBuilder();

        foreach (['comments', 'tags', 'posts', 'users'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
