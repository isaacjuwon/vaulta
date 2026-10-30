<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(table: 'oauth_accounts', callback: function (Blueprint $table): void {
            $table->id();
            $table->foreignId(column: 'user_id')->constrained(table: 'users')->cascadeOnDelete();
            $table->string(column: 'provider', length: 32);
            $table->string(column: 'provider_id', length: 191);
            $table->string(column: 'provider_email')->nullable();
            $table->string(column: 'name')->nullable();
            $table->string(column: 'nickname')->nullable();
            $table->text(column: 'avatar')->nullable();
            $table->text(column: 'access_token')->nullable();
            $table->text(column: 'refresh_token')->nullable();
            $table->timestamp(column: 'token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(columns: ['provider', 'provider_id'], name: 'oauth_accounts_provider_id_unique');
            $table->index(columns: ['user_id', 'provider'], name: 'oauth_accounts_user_provider_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'oauth_accounts');
    }
};
