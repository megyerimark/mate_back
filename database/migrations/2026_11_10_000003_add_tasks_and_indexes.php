<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Feladatok és határidők (ajánlatkéréshez kötve vagy önállóan).
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['done_at', 'due_at']);
        });

        // Kommunikációs napló: a bejegyzés szövege (hívás, e-mail, találkozó).
        Schema::table('quote_request_events', function (Blueprint $table) {
            $table->string('note', 1000)->nullable()->after('to');
        });

        // Indexek a lista, a statisztika és az ügyféladatlap lekérdezéseihez.
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('email');
            $table->index(['status', 'status_changed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['email']);
            $table->dropIndex(['status', 'status_changed_at']);
        });
        Schema::table('quote_request_events', function (Blueprint $table) {
            $table->dropColumn('note');
        });
        Schema::dropIfExists('tasks');
    }
};
