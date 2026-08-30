<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('inertia-table.views.table', 'table_views'), function (Blueprint $table) {
            $table->id();
            $table->string('table_key');
            $table->string('table_name', 120)->nullable();
            $table->string('user_id')->nullable();
            $table->string('name', 120);
            $table->json('state');
            $table->json('attributes')->nullable();
            $table->char('context_hash', 64);
            $table->char('scope_hash', 64);
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['table_key', 'user_id']);
            $table->index(['context_hash', 'is_shared']);
            $table->index(['scope_hash', 'is_default']);
            $table->unique(['scope_hash', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('inertia-table.views.table', 'table_views'));
    }
};
