<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - users.digest_time : heure d'envoi du récapitulatif du matin (HH:MM, heure de Paris ; vide = 8 h).
     * - users.digest_sent_on : dernier jour où le récapitulatif a été envoyé (évite les doublons).
     * - feed_post_reads : actualités déjà vues par chaque utilisateur (page de l'actualité ou du fil).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('digest_time', 5)->nullable();
            $table->date('digest_sent_on')->nullable();
        });

        Schema::create('feed_post_reads', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at')->nullable();
            $table->primary(['user_id', 'feed_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_post_reads');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['digest_time', 'digest_sent_on']));
    }
};
