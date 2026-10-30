<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Photo envoyée par l'utilisateur (prioritaire sur l'avatar Google).
            $table->string('photo_path')->nullable()->after('avatar');
            // Invitation en attente : empreinte du jeton envoyé par e-mail.
            $table->string('invitation_token', 64)->nullable()->unique()->after('photo_path');
            $table->timestamp('invited_at')->nullable()->after('invitation_token');
            $table->foreignId('invited_by')->nullable()->after('invited_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropUnique(['invitation_token']);
            $table->dropColumn(['photo_path', 'invitation_token', 'invited_at']);
        });
    }
};
