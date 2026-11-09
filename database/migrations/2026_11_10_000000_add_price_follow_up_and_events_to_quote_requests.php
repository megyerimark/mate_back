<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            // Az ajánlott vagy megrendelt ár forintban (az admin írja be).
            $table->unsignedInteger('price')->nullable()->after('status');
            // Mikor változott utoljára az állapot: ebből látszik, ha egy elküldött ajánlatra régóta nincs válasz.
            $table->timestamp('status_changed_at')->nullable()->after('price');
        });
        DB::table('quote_requests')->update(['status_changed_at' => DB::raw('updated_at')]);

        // Előzmények: ki, mikor, mit változtatott.
        Schema::create('quote_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // status, price, note
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_request_events');
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['price', 'status_changed_at']);
        });
    }
};
