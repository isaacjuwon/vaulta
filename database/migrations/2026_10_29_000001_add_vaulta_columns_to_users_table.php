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
        Schema::table(table: 'users', callback: function (Blueprint $table): void {
            $table->string(column: 'palette', length: 24)->default(value: 'ferrite')->after(column: 'password');
            $table->longText(column: 'avatar')->nullable()->after(column: 'palette');
            $table->boolean(column: 'is_admin')->default(value: false)->after(column: 'avatar');
            $table->boolean(column: 'is_blocked')->default(value: false)->after(column: 'is_admin');
            $table->timestamp(column: 'blocked_at')->nullable()->after(column: 'is_blocked');
            $table->timestamp(column: 'sign_in_locked_until')->nullable()->after(column: 'blocked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(table: 'users', callback: function (Blueprint $table): void {
            $table->dropColumn(columns: [
                'palette',
                'avatar',
                'is_admin',
                'is_blocked',
                'blocked_at',
                'sign_in_locked_until',
            ]);
        });
    }
};
