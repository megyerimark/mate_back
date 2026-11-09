<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Több feltöltött fájl ajánlatkérésenként. A régi document_path/document_name oszlop a korábbi kéréseknél marad.
        Schema::create('quote_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->unsignedInteger('size');
            $table->string('mime', 120)->nullable();
            $table->timestamps();
        });

        // Ügyfélvélemények: csak az adminból, valódi ügyféltől; a weboldalon csak a publikáltak látszanak.
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('company', 160)->nullable();
            $table->string('role', 120)->nullable();
            $table->text('text');
            $table->string('locale', 3)->default('all'); // hu, en vagy all
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_published')->default(false);
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->index(['is_published', 'sort']);
        });

        // Partnerek (logóval), szintén az adminból.
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('logo_path')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_published')->default(false);
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->index(['is_published', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('quote_request_documents');
    }
};
