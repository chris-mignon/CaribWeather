<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('client_id')->nullable()->index();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 40)->default('aes128gcm');
            $table->timestamps();

            $table->unique('endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
