<?php

namespace App\Http\Resources;

use App\Services\Loki\LogResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class LogCollection extends ResourceCollection
{
    public $collects = LogEntryResource::class;

    public function __construct(private readonly LogResult $result)
    {
        parent::__construct($result->entries);
    }

    public function with(Request $request): array
    {
        return ['meta' => [
            'start' => $this->result->query?->start->toIso8601String(),
            'end' => $this->result->query?->end->toIso8601String(),
            'limit' => $this->result->query?->parameters()['limit'],
            'summary' => $this->result->summary(),
        ]];
    }
}
