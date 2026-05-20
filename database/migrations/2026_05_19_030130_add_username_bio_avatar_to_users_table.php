<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('id');
            $table->text('bio')->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('bio');
        });

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $base = Str::slug(Str::before($user->email, '@'), '');
            $base = $base !== '' ? $base : 'user';
            $candidate = $base;
            $i = 1;

            while (DB::table('users')->where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
                $candidate = $base.$i++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'bio', 'avatar_path']);
        });
    }
};
