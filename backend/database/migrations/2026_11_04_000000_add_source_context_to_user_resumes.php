<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_resumes', function (Blueprint $table) {
            $table->json('source_context')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_resumes', function (Blueprint $table) {
            $table->dropColumn('source_context');
        });
    }
};
