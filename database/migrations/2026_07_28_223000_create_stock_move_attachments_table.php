<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_move_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_move_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Conserva visibles las evidencias que antes se guardaban en la única
        // columna attachment_path de cada movimiento.
        DB::table('stock_moves')->whereNotNull('attachment_path')->orderBy('id')->each(function ($move) {
            DB::table('stock_move_attachments')->insert([
                'stock_move_id' => $move->id,
                'file_path' => $move->attachment_path,
                'file_name' => basename($move->attachment_path),
                'uploaded_by' => $move->user_id,
                'created_at' => $move->created_at ?: now(),
                'updated_at' => $move->updated_at ?: now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_move_attachments');
    }
};
