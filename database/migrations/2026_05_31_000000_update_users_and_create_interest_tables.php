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
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username',191)->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('users', 'firstName')) {
                $table->string('firstName',191)->nullable()->after('username');
            }

            if (! Schema::hasColumn('users', 'lastName')) {
                $table->string('lastName',191)->nullable()->after('firstName');
            }

            if (! Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'profilePicture')) {
                $table->string('profilePicture',255)->nullable()->after('bio');
            }

            if (! Schema::hasColumn('users', 'agreeTerms')) {
                $table->boolean('agreeTerms')->default(false)->after('profilePicture');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }

            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }

            if (Schema::hasColumn('users', 'remember_token')) {
                $table->dropRememberToken();
            }

            if (Schema::hasColumn('users', 'created_at')) {
                $table->dropTimestamps();
            }
        });

        Schema::create('interest', function (Blueprint $table) {
            $table->id();
            $table->string('icon',255)->nullable();
            $table->string('name',191)->unique();
        });

        Schema::create('user_interest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('interest_id')->constrained('interest')->cascadeOnDelete();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();

            $table->unique(['user_id', 'interest_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_interest');
        Schema::dropIfExists('interest');

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'name')) {
                $table->string('name',191)->after('id');
            }

            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken()->after('password');
            }

            if (! Schema::hasColumn('users', 'created_at')) {
                $table->timestamps();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['username', 'firstName', 'lastName', 'bio', 'profilePicture', 'agreeTerms'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
