<?php

namespace App\Services\Metrics\Brand;

use App\Models\Campaign\CampaignMetricsGroup;
use App\Models\Campaign\CampaignMetricsValue;
use App\Promoter;
use App\Repositories\Brand\Performance\Components\AvgOrderPricePerformanceItem;
use App\Repositories\Brand\Performance\Components\ContentEngagementPerformanceItem;
use App\Repositories\Brand\Performance\Components\ContentGmvByTiersPerformanceItem;
use App\Repositories\Brand\Performance\Components\ContentWithGmvPerformanceItem;
use App\Repositories\Brand\Performance\Components\CreatorsApprovedPerformanceItem;
use App\Repositories\Brand\Performance\Components\GmvPerformanceItem;
use App\Repositories\Brand\Performance\Components\PerformanceItem;
use App\Repositories\Brand\Performance\Components\ProductsShippedViaShopify;
use App\Repositories\Brand\Performance\Components\ProductsShippedViaTikTok;
use App\Repositories\Brand\Performance\Components\ProductsSoldPerformanceItem;
use App\Repositories\Campaign\Metrics\MetricGroupCategories;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Optional;
use Symfony\Component\Console\Command\Command;

/**
 * Комментарий разработчика:
 * Этот класс не являеся командой сам по себе, но инстанцируется и используется в одном из классов,
 * наследующих Command.
 * Таким образом я могу вынести в админ-панель инструмент, который бы производил ту же самую операцию, что и
 * данная команда.
 * Можно, конечно, и инстанцировать Command-ы в любом другом месте кода, но в таких случаях было бы сложно
 * реализовать поведение, специфичное для определённой среды (то есть, делать что-то одно для CLI режима, и иное для
 * режима WEB).
 *
 * См. класс PersistBrandPerformanceSnapshotCommand.
 */

class PersistBrandPerformanceSnapshot
{
    protected array $ids = [];

    /** @var Command|Optional */
    protected $command = null;

    public function __construct(
        array $options = []
    )
    {
        $options += [
            'ids' => [],
            'command' => null,
        ];
        $this->ids = (function ($ids) {
            return collect(is_array($ids) ? $ids : explode(',', $ids))
                ->filter(function (string $maybeId) {
                    return is_numeric($maybeId);
                })
                ->values()
                ->all();
        })(
            $options['ids']
        );
        $this->command = $options['command'] ?? \optional();
    }

    public function runAsConsoleCommand()
    {
        $output = $this->run($this->prepareData());
        return $this->handleOutput($output);
    }

    protected function prepareData(): array
    {
        $brandQuery = Promoter::query()
            ->with([
                'partners',
            ])
            ->whereHas('data')
            ->orderByDesc('id')
        ;
        $command = $this->command;

        if ($this->ids) {
            $brandQuery
                ->whereIn('id', $this->ids);
            $command->line(
                sprintf(
                    "<comment>Only IDs: </comment><info>[ %s ]</info>",
                    join(', ', $this->ids),
                ),
            );
        } else {
            $command->comment("No filtering by ID");
        }

        return [
            'query' => $brandQuery,
        ];
    }

    protected function run(array $input): array
    {
        /**
         * @var Builder $brandQuery
         */
        [
            'query' => $brandQuery,
        ] = $input;
        $command = $this->command;

        $processedCount = 0;
        $brandQuery
            ->chunk(128, function(Collection $brandCollection) use (&$processedCount, $command) {
                $brandCollection
                    ->each(function(Promoter $brand) use ($command) {
                        $command->comment(
                            sprintf(
                                'Brand: [#%d] ...',
                                $brand->id,
                            )
                        );

                        $brandHandleOutput = $this->handleBrand($brand);
                    });

                $processedCount++;
            });

        return [];
    }

    public function handleBrand(Promoter $brand): array
    {
        $responseData = [];
        $hasChanges = false;
        $command = $this->command;

        /*
         * Persist metrics
         */
        $metricAspectsToValues = [];
        /** @var PerformanceItem $performanceItem */
        foreach (
            [
                new ContentWithGmvPerformanceItem($brand),
                new ProductsSoldPerformanceItem($brand),
                new GmvPerformanceItem($brand),
                new ContentGmvByTiersPerformanceItem($brand),
                new AvgOrderPricePerformanceItem($brand),
                new ContentEngagementPerformanceItem($brand),
                new CreatorsApprovedPerformanceItem($brand),
                #
                new ProductsShippedViaTikTok($brand),
                new ProductsShippedViaShopify($brand),
            ] as $performanceItem
        ) {
            $metricAspectsToValues += $performanceItem->calculateAspectsValues();
        }

        $metricGroup = (new CampaignMetricsGroup([
            'brand_id' => $brand->id,
            'metric_group_category_id' => MetricGroupCategories::ID_BRAND_PERFORMANCE,
        ]));
        $metricGroup->save();

        foreach ($metricAspectsToValues as $metricAspectId => $value) {
            $metricValue = (new CampaignMetricsValue([
                'campaign_metrics_group_id' => $metricGroup->id,
                'metric_aspect_id' => $metricAspectId,
                'value' => $value,
            ]));
            $metricValue->save();
        }

        $command->info('- metrics saved');

        return $responseData;
    }

    protected function handleOutput(array $output): int
    {
        return 0;
    }
}
