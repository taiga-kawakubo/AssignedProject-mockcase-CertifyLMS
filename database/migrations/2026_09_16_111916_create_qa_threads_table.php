<?php

declare(strict_types=1);

use App\Enums\QaThreadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 受講者が質問掲示板に投稿した際に保存されるテーブル。
     * resolved_atは、未解決の質問が解決済みになった時の日付が入力。
     */
    public function up(): void
    {
        Schema::create('qa_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('certification_id')->constrained('certifications')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status', 20)->default(QaThreadStatus::Open->value);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['certification_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_threads');
    }
};
