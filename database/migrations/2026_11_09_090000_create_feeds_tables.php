<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fils d'actualité : chaque collaborateur peut en créer et s'abonner à ceux des autres.
 * Les actualités ont un texte riche, une date et des pièces jointes (table attachments, partagée avec les tâches).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 16)->default('#6366f1');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Abonnés : ils reçoivent un e-mail à chaque nouvelle actualité et voient le fil dans le menu de gauche.
        Schema::create('feed_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['feed_id', 'user_id']);
        });

        Schema::create('feed_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->date('published_on');
            $table->timestamps();
            $table->index(['feed_id', 'published_on']);
        });

        // Une pièce jointe appartient à une tâche ou à une actualité.
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->change();
            $table->foreignId('feed_post_id')->nullable()->after('task_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feed_post_id');
        });
        Schema::dropIfExists('feed_posts');
        Schema::dropIfExists('feed_user');
        Schema::dropIfExists('feeds');
    }
};
