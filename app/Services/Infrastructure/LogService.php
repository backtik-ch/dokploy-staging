<?php

namespace App\Services\Infrastructure;

use App\Exceptions\LokiException;
use App\Models\User;
use App\Services\Loki\LogQuery;
use App\Services\Loki\LogQueryFactory;
use App\Services\Loki\LogResult;
use App\Services\Loki\LokiService;
use Illuminate\Validation\ValidationException;

class LogService
{
    public function __construct(
        private readonly LogQueryFactory $queries,
        private readonly LokiService $loki,
        private readonly RemoteOperation $remote,
    ) {}

    public function search(array $filters, User $user): LogResult
    {
        ApiAccess::authorize($user, 'logs:read');
        $query = $this->query($filters);

        return $this->remote->run(fn () => $this->loki->search($query));
    }

    public function labels(array $filters, User $user): array
    {
        ApiAccess::authorize($user, 'logs:read');
        $query = $this->query($filters);

        return array_values($this->remote->run(fn () => $this->loki->labels($query)));
    }

    public function labelValues(string $label, array $filters, User $user): array
    {
        ApiAccess::authorize($user, 'logs:read');
        $query = $this->query($filters);

        return array_values($this->remote->run(fn () => $this->loki->labelValues($label, $query)));
    }

    private function query(array $filters): LogQuery
    {
        try {
            $query = $this->queries->make(['period' => '1h', 'limit' => 1000, ...$filters], now('UTC')->toIso8601String(), $filters['search'] ?? '');
            $query->expression();

            return $query;
        } catch (LokiException $exception) {
            throw ValidationException::withMessages(['filters' => $exception->getMessage()]);
        }
    }
}
