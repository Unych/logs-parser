<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Http\Requests\StatsFilterRequest;
use App\Models\LogEntry;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class StatsAggregator
{
    public function __construct(
        private readonly int $cacheTtl,
    ) {}

    public function requestsByDay(array $filters): array
    {
        return $this->cached('requests_by_day', $filters, function () use ($filters) {
            $days = $this->datesInRange($filters['date_from'], $filters['date_to']);

            // заполняем нулями весь диапазон, чтобы дни без запросов не выпадали из графика
            $humans = array_fill_keys($days, 0);
            $bots   = array_fill_keys($days, 0);

            $rows   = $this->baseQuery($filters)
                ->selectRaw('DATE(requested_at) as day, is_bot, COUNT(*) as cnt')
                ->groupBy('day', 'is_bot')
                ->get();

            foreach ($rows as $row) {
                $day   = (string)$row->day;
                $count = (int)$row->cnt;

                if (!isset($humans[$day])) {
                    continue;
                }

                if ((int) $row->is_bot === 1) {
                    $bots[$day] = $count;
                } else {
                    $humans[$day] = $count;
                }
            }

            return [
                'labels' => array_values($days),
                'humans' => array_values($humans),
                'bots'   => array_values($bots),
            ];
        });
    }

    public function browserShareByDay(array $filters): array
    {
        return $this->cached('browser_share', $filters, function () use ($filters) {
            $topBrowsers = $this->baseQuery($filters)
                ->selectRaw('browser, COUNT(*) as cnt')
                ->whereNotNull('browser')
                ->where('browser', '!=', '')
                ->groupBy('browser')
                ->orderByDesc('cnt')
                ->limit(3)
                ->pluck('browser')
                ->all();

            $days = $this->datesInRange($filters['date_from'], $filters['date_to']);

            if ($topBrowsers === []) {
                return [
                    'labels'   => array_values($days),
                    'browsers' => [],
                    'series'   => [],
                ];
            }

            $totalsByDay = $this->baseQuery($filters)
                ->selectRaw('DATE(requested_at) as day, COUNT(*) as cnt')
                ->groupBy('day')
                ->pluck('cnt', 'day')
                ->all();

            $rows = $this->baseQuery($filters)
                ->whereIn('browser', $topBrowsers)
                ->selectRaw('DATE(requested_at) as day, browser, COUNT(*) as cnt')
                ->groupBy('day', 'browser')
                ->get();

            $series = [];
            foreach ($topBrowsers as $browser) {
                $series[$browser] = array_fill_keys($days, 0.0);
            }
            foreach ($rows as $row) {
                $day = (string) $row->day;
                if (!isset($totalsByDay[$day]) || $totalsByDay[$day] === 0) {
                    continue;
                }
                $sharePercent = round(((int) $row->cnt) * 100.0 / (int) $totalsByDay[$day], 2);
                $series[$row->browser][$day] = $sharePercent;
            }

            $flatSeries = [];
            foreach ($series as $browser => $sharesByDay) {
                $flatSeries[$browser] = array_values($sharesByDay);
            }

            return [
                'labels'   => array_values($days),
                'browsers' => $topBrowsers,
                'series'   => $flatSeries,
            ];
        });
    }

    public function dailyTable(array $filters): array
    {
        return $this->cached('daily_table', $filters, function () use ($filters) {
            $totals = $this->baseQuery($filters)
                ->selectRaw('DATE(requested_at) as day, COUNT(*) as cnt')
                ->groupBy('day')
                ->pluck('cnt', 'day')
                ->all();

            $topUrls     = $this->topPerDay($filters, 'url');
            $topBrowsers = $this->topPerDay($filters, 'browser');

            $rows = [];
            foreach ($totals as $day => $count) {
                $rows[] = [
                    'date'        => (string) $day,
                    'requests'    => (int) $count,
                    'top_url'     => $topUrls[$day] ?? null,
                    'top_browser' => $topBrowsers[$day] ?? null,
                ];
            }

            return $this->sortTableRows($rows, $filters['sort'], $filters['dir']);
        });
    }

    private function topPerDay(array $filters, string $column): array
    {
        if (!in_array($column, ['url', 'browser'], true)) {
            throw new \InvalidArgumentException("Unsupported column: {$column}");
        }

        $baseQuery   = $this->baseQuery($filters);
        $bindings    = $baseQuery->getBindings();
        $baseSql     = $baseQuery->toSql();

        $wrappedSql = sprintf(
            "SELECT day, value FROM (
                SELECT day, value, cnt,
                       ROW_NUMBER() OVER (PARTITION BY day ORDER BY cnt DESC, value ASC) AS rn
                FROM (
                    SELECT DATE(requested_at) AS day, %s AS value, COUNT(*) AS cnt
                    FROM (%s) AS filtered
                    GROUP BY day, value
                ) AS counted
            ) AS ranked
            WHERE rn = 1",
            $column,
            $baseSql
        );

        $rows = DB::select($wrappedSql, $bindings);

        $topValueByDay = [];
        foreach ($rows as $row) {
            $topValueByDay[(string) $row->day] = (string) $row->value;
        }
        return $topValueByDay;
    }

    private function baseQuery(array $filters): Builder
    {
        $query = DB::table((new LogEntry)->getTable())
            ->whereBetween('requested_at', [
                $filters['date_from'].' 00:00:00',
                $filters['date_to'].' 23:59:59',
            ]);

        if (!empty($filters['os'])) {
            $query->where('os', $filters['os']);
        }
        if (!empty($filters['arch'])) {
            $query->where('arch', $filters['arch']);
        }

        $botsFilter = $filters['bots'] ?? StatsFilterRequest::BOTS_ALL;
        if ($botsFilter === StatsFilterRequest::BOTS_BOTS) {
            $query->where('is_bot', true);
        } elseif ($botsFilter === StatsFilterRequest::BOTS_HUMANS) {
            $query->where('is_bot', false);
        }

        return $query;
    }

    private function sortTableRows(array $rows, string $sort, string $dir): array
    {
        $sortKeyMap = [
            'date'        => 'date',
            'requests'    => 'requests',
            'top_url'     => 'top_url',
            'top_browser' => 'top_browser',
        ];
        $key        = $sortKeyMap[$sort] ?? 'date';
        $ascending  = $dir === 'asc';

        usort($rows, function ($rowA, $rowB) use ($key, $ascending) {
            $valueA     = $rowA[$key] ?? '';
            $valueB     = $rowB[$key] ?? '';
            $comparison = is_int($valueA) && is_int($valueB)
                ? $valueA <=> $valueB
                : strcmp((string) $valueA, (string) $valueB);
            return $ascending ? $comparison : -$comparison;
        });

        return $rows;
    }

    private function datesInRange(string $from, string $to): array
    {
        $current = new DateTimeImmutable($from);
        $end     = new DateTimeImmutable($to);

        $days = [];
        while ($current <= $end) {
            $days[]  = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }

        return $days;
    }

    private function cached(string $section, array $filters, callable $resolver)
    {
        $cacheKey = $this->cacheKey($section, $filters);
        return Cache::remember($cacheKey, $this->cacheTtl, $resolver);
    }

    private function cacheKey(string $section, array $filters): string
    {
        ksort($filters);
        return 'stats:'.$section.':'.sha1((string) json_encode($filters));
    }
}
