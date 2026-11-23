<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('landlord_name');
            $table->string('landlord_phone', 30)->nullable();
            $table->text('property_address');
            $table->date('start_date');
            $table->date('end_date')->index();
            $table->unsignedBigInteger('rent_kobo');
            $table->string('rent_frequency', 20)->default('yearly'); // monthly|termly|yearly
            $table->string('status', 20)->default('active')->index(); // active|expired|terminated|disputed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
