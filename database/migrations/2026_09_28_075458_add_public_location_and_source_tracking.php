<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('country_code', 2)->default('ID');
            $table->string('province')->nullable();
        });
        Schema::table('job_daily_metrics', function (Blueprint $table) {
            $table->index('job_posting_id', 'job_metrics_posting_index');
            $table->dropUnique(['job_posting_id', 'day', 'event']);
            $table->string('source', 20)->default('direct');
            $table->unique(['job_posting_id', 'day', 'event', 'source'], 'job_metric_daily_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', fn (Blueprint $table) => $table->dropColumn(['country_code', 'province']));
        // Source aggregates are retained to avoid destructive merging on rollback.
    }
};
