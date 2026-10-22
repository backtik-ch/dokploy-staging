<?php

namespace App\Services\Loki;

use App\Exceptions\LokiException;

class LokiService
{
    public function __construct(private readonly LokiClient $client) {}

    public function search(LogQuery $query): LogResult
    {
        $data = $this->client->cached('query_range', $query->parameters(), 30);

        if (($data['resultType'] ?? null) !== 'streams' || ! is_array($data['result'] ?? null)) {
            throw new LokiException('La réponse de Loki ne contient pas de flux de logs.');
        }

        $entries = collect($data['result'])->flatMap(function (array $stream): array {
            return array_map(fn (array $value): array => LogEntry::fromLoki($stream['stream'], $value), $stream['values']);
        })->sort(fn (array $a, array $b): int => strcmp($b['timestamp'], $a['timestamp']))->values();

        return new LogResult($entries, $query);
    }

    public function labels(LogQuery $query): array
    {
        return $this->options($this->client->cached('labels', [...$this->metadataBounds($query), 'query' => $query->selector()]));
    }

    public function labelValues(string $label, LogQuery $query): array
    {
        LogQuery::validateLabel($label);

        return $this->options($this->client->cached('label/'.$label.'/values', [...$this->metadataBounds($query), 'query' => $query->selector()]));
    }

    private function metadataBounds(LogQuery $query): array
    {
        // Rounded bounds let successive keystrokes and refreshes share discovery requests.
        return ['start' => $query->start->startOfMinute()->format('U').'000000000', 'end' => $query->end->endOfMinute()->format('U').'000000000'];
    }

    private function options(array $values): array
    {
        $values = array_values(array_filter($values, 'is_string'));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return array_combine($values, $values);
    }
}
