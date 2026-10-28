<?php

use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

/** The tables the JSON:API resource tests read: what their afterRefreshingDatabase() created. Runs after the "laravel" set. */
return new class extends Migration
{
    public function up(): void
    {
        $schema = app('db')->connection()->getSchemaBuilder();

        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('title');
            $table->text('content');
            $table->timestamps();
        });

        $schema->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('timezone')->nullable();
        });

        $schema->create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('name');
            $table->boolean('personal_team');
        });

        $schema->create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id');
            $table->foreignId('user_id');
            $table->string('role')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id']);
        });

        $schema->create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique();
            $table->foreignId('user_id')->index()->nullable();
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $schema = app('db')->connection()->getSchemaBuilder();

        foreach (['comments', 'team_user', 'teams', 'profiles', 'posts'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
