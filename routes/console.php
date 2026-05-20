<?php

use App\Models\Staging;
use App\Services\DeployService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('staging:cleanup-old', function () {
    $cutoff = now()->subMonth();

    Staging::query()
        ->where('created_at', '<', $cutoff)
        ->orderBy('id')
        ->chunkById(50, function ($stagings): void {
            foreach ($stagings as $staging) {
                try {
                    app(DeployService::class)->deploy(
                        $staging->project,
                        'delete',
                        (string) $staging->pr_number,
                        (string) $staging->branch,
                    );

                    $this->info("Deleted staging #{$staging->id} ({$staging->pr_number})");
                } catch (\Throwable $e) {
                    Log::error('Failed to cleanup staging', [
                        'staging_id' => $staging->id,
                        'project_id' => $staging->project_id,
                        'reference' => $staging->pr_number,
                        'error' => $e->getMessage(),
                    ]);

                    $this->error("Failed staging #{$staging->id}: {$e->getMessage()}");
                }
            }
        });
})->purpose('Delete stagings older than one month')
    ->dailyAt('02:00');
