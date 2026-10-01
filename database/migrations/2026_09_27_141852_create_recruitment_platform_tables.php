<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('ACTIVE')->index();
            $table->string('phone', 30)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->integer('credits')->default(0);
            $table->integer('reserved_credits')->default(0);
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->string('provider_email');
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
        });
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->unique()->constrained('users');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('industry');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('position');
            $table->string('website')->nullable();
            $table->string('location');
            $table->text('description');
            $table->string('verification_status')->default('NOT_SUBMITTED')->index();
            $table->string('status')->default('ACTIVE');
            $table->text('review_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->string('employment_type');
            $table->string('level');
            $table->unsignedInteger('vacancies')->default(1);
            $table->string('location')->index();
            $table->string('work_arrangement');
            $table->unsignedBigInteger('salary_min')->nullable();
            $table->unsignedBigInteger('salary_max')->nullable();
            $table->boolean('salary_visible')->default(false);
            $table->string('salary_period')->default('bulan');
            $table->text('description');
            $table->text('responsibilities')->nullable();
            $table->text('qualifications');
            $table->text('benefits')->nullable();
            $table->string('education')->nullable();
            $table->string('experience')->nullable();
            $table->string('skills')->nullable();
            $table->string('method');
            $table->text('external_url')->nullable();
            $table->string('status')->default('DRAFT');
            $table->text('review_reason')->nullable();
            $table->boolean('credit_reserved')->default(false);
            $table->timestamp('deadline');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('distribution_cursor')->default(0);
            $table->timestamp('distributed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index(['company_id', 'status']);
        });
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('cv_path');
            $table->string('portfolio')->nullable();
            $table->string('status')->default('NEW');
            $table->timestamps();
            $table->unique(['job_posting_id', 'user_id']);
            $table->index(['job_posting_id', 'status']);
        });
        Schema::create('job_saves', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained();
            $table->foreignId('job_posting_id')->constrained();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'job_posting_id']);
        });
        Schema::create('credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->integer('amount');
            $table->string('type');
            $table->string('operation_key')->unique();
            $table->string('reason');
            $table->timestamps();
        });
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('credits');
            $table->text('description')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('package_id')->constrained();
            $table->string('package_name');
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('credits');
            $table->string('status')->default('PENDING')->index();
            $table->string('provider_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('refund_status')->default('NONE');
            $table->string('refund_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->boolean('active')->default(false);
            $table->boolean('suppressed')->default(false);
            $table->json('preferences')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained();
            $table->foreignId('subscription_id')->constrained();
            $table->string('status')->default('PENDING')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['job_posting_id', 'subscription_id']);
        });
        Schema::create('job_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('OPEN');
            $table->timestamps();
        });
        Schema::create('job_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained();
            $table->date('day');
            $table->string('event');
            $table->unsignedBigInteger('count')->default(0);
            $table->unique(['job_posting_id', 'day', 'event']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action');
            $table->string('subject');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'job_daily_metrics', 'job_reports', 'email_deliveries', 'subscriptions', 'purchases', 'packages', 'credit_entries', 'job_saves', 'job_applications', 'job_postings', 'companies', 'social_accounts', 'user_roles', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['status', 'phone', 'last_login_at', 'credits', 'reserved_credits']));
    }
};
