<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('entities', 'status')) {
            DB::table('entities')
                ->where('type', 'cliente')
                ->whereNull('status')
                ->update(['status' => 'active']);
        }

        if (Schema::hasColumn('entities', 'user_id') && Schema::hasColumn('entities', 'email')) {
            DB::table('entities')
                ->where('type', 'cliente')
                ->whereNotNull('email')
                ->whereNull('user_id')
                ->orderBy('id')
                ->get()
                ->each(function ($entity) {
                    $userId = User::where('email', $entity->email)->value('id');

                    if ($userId) {
                        DB::table('entities')->where('id', $entity->id)->update(['user_id' => $userId]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
