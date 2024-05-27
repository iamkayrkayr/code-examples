<?php

namespace App\Console\Commands\Metrics;

use App\Services\Metrics\Brand\PersistBrandPerformanceSnapshot;
use Illuminate\Console\Command;

/**
 * Комментарий разработчика:
 * Подробнее: см. класс PersistBrandPerformanceSnapshot.
 */

class PersistBrandPerformanceSnapshotCommand extends Command
{
    protected $signature = 'metrics:persist-brand-performance'
    . ' {--ids= : user ids of Promoters}'
    ;

    protected $description = 'Persist current performance values';

    public function handle(): int
    {
        return (new PersistBrandPerformanceSnapshot([
            'ids' => $this->option('ids'),
            'command' => $this,
        ]))
            ->runAsConsoleCommand();
    }
}
