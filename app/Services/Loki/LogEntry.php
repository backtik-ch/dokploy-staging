<?php

namespace App\Services\Loki;

use Carbon\CarbonImmutable;

final class LogEntry
{
    public static function fromLoki(array $labels, array $value): array
    {
        $timestamp = (string) $value[0];
        $raw = (string) $value[1];
        $decoded = json_decode($raw, true);
        $fields = is_array($decoded) ? $decoded : [];
        $metadata = is_array($value[2] ?? null) ? $value[2] : [];
        $message = $fields['message'] ?? $fields['msg'] ?? $fields['log'] ?? $raw;
        $level = $labels['level'] ?? $labels['severity'] ?? $metadata['level'] ?? $metadata['severity_text']
            ?? $fields['level_name'] ?? $fields['level'] ?? $fields['severity'] ?? null;

        if (! is_scalar($level) || ! filled($level)) {
            preg_match('/(?:^|[\s\[.])(TRACE|DEBUG|INFO|NOTICE|WARN(?:ING)?|ERROR|CRITICAL|ALERT|EMERG(?:ENCY)?|FATAL)(?=$|[\s:\]])/i', $raw, $matches);
            $level = $matches[1] ?? 'unknown';
        }

        $level = match (strtolower((string) $level)) {
            'warn' => 'warning',
            'fatal', 'emerg', 'emergency', 'alert' => 'critical',
            default => strtolower((string) $level),
        };

        ksort($labels);

        return [
            'id' => hash('sha256', serialize([$labels, $timestamp, $raw, $metadata])),
            'timestamp' => $timestamp,
            'datetime' => CarbonImmutable::createFromTimestampUTC(substr($timestamp, 0, -9).'.'.substr($timestamp, -9, 6))->format('Y-m-d\TH:i:s.uP'),
            'time' => CarbonImmutable::createFromTimestampUTC(substr($timestamp, 0, -9).'.'.substr($timestamp, -9, 6))->format('H:i:s.v'),
            'level' => $level,
            'project' => $labels[config('loki.project_label')] ?? '—',
            'service' => $labels['service'] ?? $labels['service_name'] ?? $labels['container_name'] ?? $labels['app'] ?? $labels['job'] ?? '—',
            'environment' => $labels['env'] ?? $labels['environment'] ?? '—',
            'message' => is_string($message) ? $message : $raw,
            'raw' => $raw,
            'pretty' => $fields ? json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : $raw,
            'labels' => $labels,
            'metadata' => $metadata,
            'stream' => hash('sha256', serialize($labels)),
        ];
    }

    public static function color(string $level): string
    {
        return match ($level) {
            'error', 'critical' => 'danger',
            'warning' => 'warning',
            'info', 'notice' => 'info',
            'debug', 'trace' => 'gray',
            default => 'gray',
        };
    }
}
