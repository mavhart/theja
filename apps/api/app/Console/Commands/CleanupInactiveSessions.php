<?php

namespace App\Console\Commands;

use App\Models\DeviceSession;
use Illuminate\Console\Command;

class CleanupInactiveSessions extends Command
{
    protected $signature   = 'theja:cleanup-sessions {--hours=8 : Ore di inattività prima di invalidare la sessione}';
    protected $description = 'Invalida le device_session inattive da più di N ore (default: 8)';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $count = DeviceSession::where('is_active', true)
            ->where('last_active_at', '<', now()->subHours($hours))
            ->update(['is_active' => false]);

        $this->info("Invalidate {$count} sessioni inattive da più di {$hours} ore.");

        return self::SUCCESS;
    }
}
