<?php

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function timeToSeconds(?string $time): int
{
    if (!$time || !preg_match('/^\d{2}:\d{2}:\d{2}/', $time)) {
        return 0;
    }

    [$h, $m, $s] = array_map('intval', explode(':', substr($time, 0, 8)));
    return ($h * 3600) + ($m * 60) + $s;
}

function secondsToTime(float $seconds): string
{
    $totalSeconds = (int) round($seconds);
    $hours = intdiv($totalSeconds, 3600);
    $minutes = intdiv($totalSeconds % 3600, 60);
    $secs = $totalSeconds % 60;

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

function medianFromTimes(array $times): string
{
    if (empty($times)) {
        return '00:00:00';
    }

    sort($times);
    $count = count($times);
    $mid = intdiv($count, 2);

    if ($count % 2 === 0) {
        $median = ($times[$mid - 1] + $times[$mid]) / 2;
    } else {
        $median = $times[$mid];
    }

    return secondsToTime((float) $median);
}