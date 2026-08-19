<?php

namespace App\Core\Helpers;

use DateTime;

class DateHelper
{
    public static function today(): string
    {
        return date('Y-m-d');
    }
    public static function rangeDays(int $days): array
    {
        $end = new DateTime();
        $start = (new DateTime())->modify("-{$days} days");

        return [
            'start' => $start->format('Y-m-d 00:00:00'),
            'end'   => $end->format('Y-m-d 23:59:59')
        ];
    }

    public static function lastMonths(int $months): array
    {
        $end = new DateTime();
        $start = (new DateTime())->modify("-{$months} months");

        return [
            'start' => $start->format('Y-m-01'),
            'end'   => $end->format('Y-m-t')
        ];
    }

    public static function formatLabel(string $date): string
    {
        return (new DateTime($date))->format('M d');
    }
}