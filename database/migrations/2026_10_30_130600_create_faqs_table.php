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
        Schema::create(table: 'faqs', callback: static function (Blueprint $table): void {
            $table->id();
            $table->string(column: 'question', length: 255);
            $table->text(column: 'answer');
            $table->unsignedInteger(column: 'sort_order')->default(value: 0)->index();
            $table->boolean(column: 'is_published')->default(value: true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'faqs');
    }
};
