<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['domain_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_admins');
    }
};
