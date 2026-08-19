<?php

namespace App\Core\Helpers;

class ChartHelper
{
    public static function dataset(array $rows, string $labelKey, string $valueKey): array
    {
        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $labels[] = $row[$labelKey];
            $values[] = (int)$row[$valueKey];
        }

        return [
            'labels' => $labels,
            'data'   => $values
        ];
    }

    public static function statusChart(array $rows): array
    {
        return self::dataset($rows, 'status', 'total');
    }

    public static function dateChart(array $rows): array
    {
        return self::dataset($rows, 'date', 'total');
    }
}