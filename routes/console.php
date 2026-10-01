<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('jobs:distribute')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('platform:maintain')->everyMinute()->withoutOverlapping();
Artisan::command('app:grant-admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user || $user->status !== 'ACTIVE') {
        $this->error('Akun aktif harus login Google terlebih dahulu.');

        return 1;
    }
    $role = Role::firstOrCreate(['name' => Role::ADMIN]);
    $user->roles()->syncWithoutDetaching([$role->id]);
    DB::table('audit_logs')->insert(['user_id' => $user->id, 'action' => 'admin.provision', 'subject' => 'user:'.$user->id, 'reason' => 'Artisan local operator', 'created_at' => now()]);
    $this->info('Admin diberikan. Produksi tetap memerlukan kontrol MFA sebelum ADMIN_ACCESS_ENABLED=true.');

    return 0;
})->purpose('Provision an existing verified account as administrator');
