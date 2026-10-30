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
        Schema::create(table: 'authenticators', callback: function (Blueprint $table): void {
            $table->id();
            $table->foreignId(column: 'user_id')->constrained()->cascadeOnDelete();
            $table->string(column: 'issuer', length: 100);
            $table->string(column: 'account', length: 180);
            $table->text(column: 'secret');
            $table->string(column: 'color', length: 20)->default(value: 'zinc');
            $table->boolean(column: 'is_favorite')->default(value: false);
            $table->timestamps();

            $table->index(columns: ['user_id', 'is_favorite', 'issuer']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'authenticators');
    }
};
