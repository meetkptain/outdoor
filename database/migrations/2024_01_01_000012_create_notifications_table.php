<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            $table->enum('type', ['email', 'sms', 'push'])->default('email');
            $table->string('template'); // reservation_confirmation, reminder, etc.
            $table->string('recipient');
            
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])
                  ->default('pending');
            
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            
            // Pour email
            $table->string('message_id')->nullable(); // Mailgun message ID
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            $table->index('reservation_id');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
