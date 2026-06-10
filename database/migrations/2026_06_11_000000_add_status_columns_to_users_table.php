<?php

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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_online')) {
                $column = $table->boolean('is_online')->default(false);

                if (Schema::hasColumn('users', 'agreeTerms')) {
                    $column->after('agreeTerms');
                }
            }

            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $column = $table->timestamp('last_seen_at')->nullable();

                if (Schema::hasColumn('users', 'is_online')) {
                    $column->after('is_online');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['last_seen_at', 'is_online'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
