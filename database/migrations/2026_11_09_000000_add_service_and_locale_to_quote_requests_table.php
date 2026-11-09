<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('service', 20)->default('translation')->after('phone');
            $table->string('interpreting_type', 20)->nullable()->after('service');
            $table->string('locale', 2)->default('hu')->after('interpreting_type');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['service', 'interpreting_type', 'locale']);
        });
    }
};
