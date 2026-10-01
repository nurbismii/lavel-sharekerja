<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            foreach (['category', 'employment_type', 'level', 'location', 'work_arrangement', 'method'] as $column) {
                $table->string($column)->nullable()->change();
            }
            $table->text('description')->nullable()->change();
            $table->text('qualifications')->nullable()->change();
            $table->timestamp('deadline')->nullable()->change();
        });
        Schema::table('companies', fn (Blueprint $table) => $table->string('logo_path')->nullable());
        Schema::table('users', function (Blueprint $table) {
            $table->string('location')->nullable();
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('consent_version')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
        });
        Schema::table('email_deliveries', function (Blueprint $table) {
            $table->timestamp('queued_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('logo_path'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('location'));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['consent_version', 'unsubscribed_at']));
        Schema::table('email_deliveries', fn (Blueprint $table) => $table->dropColumn('queued_at'));
        // Keep nullable draft fields to avoid destroying partially completed drafts on rollback.
    }
};
