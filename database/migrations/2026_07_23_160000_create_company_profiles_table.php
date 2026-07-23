<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('legal_name')->nullable();
            $table->string('business_type', 80)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('industry', 120)->nullable();
            $table->string('business_scale', 80)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('pic_role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('invoice_prefix', 20)->default('INV');
            $table->string('bank_name')->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->string('bank_holder')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('brand_color', 20)->default('#52772c');
            $table->string('logo_path')->nullable();
            $table->string('invoice_template', 50)->default('professional');
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('default_due_days')->default(14);
            $table->unsignedSmallInteger('reminder_days_before_due')->default(3);
            $table->string('numbering_reset', 20)->default('yearly');
            $table->boolean('is_default')->default(true)->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_name', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
