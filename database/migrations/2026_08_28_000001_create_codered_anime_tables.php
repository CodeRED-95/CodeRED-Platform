<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anime', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('poster_url', 2048)->nullable();
            $table->string('banner_url', 2048)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->index(['status', 'year']);
        });

        Schema::create('anime_external_ids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('anime_id')->constrained('anime')->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_id');
            $table->string('external_slug')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->unique(['anime_id', 'provider']);
            $table->index(['provider', 'external_slug']);
        });

        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('anime_id')->constrained('anime')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->unique(['anime_id', 'number']);
            $table->index(['anime_id', 'year']);
        });

        Schema::create('episodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('anime_id')->constrained('anime')->cascadeOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('seasons')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('language')->default('sub');
            $table->string('poster_url', 2048)->nullable();
            $table->timestamp('aired_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->unique(['anime_id', 'number']);
            $table->index(['season_id', 'number']);
            $table->index(['anime_id', 'language']);
        });

        Schema::create('episode_servers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->cascadeOnDelete();
            $table->string('provider');
            $table->string('server_id');
            $table->string('name');
            $table->string('type')->default('stream');
            $table->string('language')->default('sub');
            $table->string('url', 2048)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->string('status')->default('available');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['episode_id', 'provider', 'server_id']);
            $table->index(['episode_id', 'priority']);
            $table->index(['provider', 'status']);
        });

        Schema::create('anime_metadata', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('anime_id')->constrained('anime')->cascadeOnDelete();
            $table->string('provider')->default('anilist');
            $table->string('external_id')->nullable();
            $table->json('titles')->nullable();
            $table->json('synonyms')->nullable();
            $table->json('genres')->nullable();
            $table->json('studios')->nullable();
            $table->json('relations')->nullable();
            $table->json('characters')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['anime_id', 'provider']);
            $table->index(['provider', 'external_id']);
            $table->index('synced_at');
        });

        Schema::create('provider_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('bucket');
            $table->string('cache_key');
            $table->json('payload')->nullable();
            $table->string('status')->default('fresh');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'bucket', 'cache_key']);
            $table->index(['provider', 'bucket']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_cache');
        Schema::dropIfExists('anime_metadata');
        Schema::dropIfExists('episode_servers');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('anime_external_ids');
        Schema::dropIfExists('anime');
    }
};
