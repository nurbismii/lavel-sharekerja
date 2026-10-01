<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CreditConcurrencyTest extends TestCase
{
    public function test_two_processes_cannot_reserve_the_same_credit(): void
    {
        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Requires a dedicated MySQL/MariaDB testing database.');
        }
        $database = config('database.connections.'.config('database.default').'.database');
        if ($database !== 'sharekerja_concurrency_testing') {
            $this->markTestSkipped('Run only against sharekerja_concurrency_testing; migrate:fresh must never touch another database.');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        $owner = User::factory()->create(['credits' => 1]);
        $owner->roles()->attach(Role::firstOrCreate(['name' => Role::RECRUITER]));
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $jobs = JobPosting::factory()->count(2)->create(['company_id' => $company->id]);
        $processes = [];
        $start = microtime(true) + 1;
        foreach ($jobs as $job) {
            $code = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); while(microtime(true)<'.sprintf('%.6f', $start).'){usleep(1000);} try{app(App\\Services\\PublicationService::class)->transition(App\\Models\\JobPosting::findOrFail('.$job->id.'),"submit",App\\Models\\User::findOrFail('.$owner->id.')); exit(0);}catch(Illuminate\\Validation\\ValidationException $e){exit(2);}';
            $environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => config('database.default'), 'DB_DATABASE' => $database, 'DB_HOST' => config('database.connections.'.config('database.default').'.host'), 'DB_PORT' => (string) config('database.connections.'.config('database.default').'.port'), 'DB_USERNAME' => config('database.connections.'.config('database.default').'.username'), 'DB_PASSWORD' => (string) config('database.connections.'.config('database.default').'.password')]);
            $pipes = [];
            $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $codes = [];
        foreach ($processes as [$process,$pipes]) {
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $codes[] = proc_close($process);
            $this->assertSame('', $error, $output);
        }
        sort($codes);
        $this->assertSame([0, 2], $codes);
        $this->assertSame(1, $owner->fresh()->credits);
        $this->assertSame(1, $owner->fresh()->reserved_credits);
        $this->assertSame(1, JobPosting::where('status', JobPosting::PENDING)->count());
    }
}
