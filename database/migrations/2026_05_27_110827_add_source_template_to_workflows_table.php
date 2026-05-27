<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('source_template')->nullable()->after('trigger_type');
            $table->index(['user_id', 'source_template']);
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source_template']);
            $table->dropColumn('source_template');
        });
    }
};
