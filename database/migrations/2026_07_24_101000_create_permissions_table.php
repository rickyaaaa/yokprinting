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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 120)->unique();
            $table->string('module', 80)->index();
            $table->string('action', 80)->index();
            $table->string('guard_name', 40)->default('web')->index();
            $table->text('description')->nullable();
            $table->string('risk_level', 30)->default('low')->index();
            $table->boolean('is_system')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['guard_name', 'module', 'action']);
            $table->index(['module', 'sort_order']);
            $table->index(['risk_level', 'sort_order']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->json('constraints')->nullable();
            $table->timestamps();

            $table->primary(['role_id', 'permission_id']);
            $table->index('permission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }
};
