<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('guest_count');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_price', 12, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->text('special_requests')->nullable();
            $table->timestamps();
            
            // Index untuk pencarian
            $table->index(['user_id', 'status']);
            $table->index(['property_id', 'check_in', 'check_out']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};