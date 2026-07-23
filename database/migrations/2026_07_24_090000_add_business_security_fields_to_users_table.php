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
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('email_verified_at');
            $table->string('role', 80)->default('owner')->after('company_name')->index();
            $table->string('status', 40)->default('active')->after('role')->index();
            $table->string('phone', 50)->nullable()->after('status');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('job_title');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->json('security_preferences')->nullable()->after('last_login_ip');

            $table->index(['status', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'role']);
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'company_name',
                'role',
                'status',
                'phone',
                'job_title',
                'avatar_path',
                'last_login_at',
                'last_login_ip',
                'security_preferences',
            ]);
        });
    }
};
