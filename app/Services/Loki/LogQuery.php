<?php

namespace App\Services\Loki;

use App\Exceptions\LokiException;
use Carbon\CarbonImmutable;

final readonly class LogQuery
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $labels = [],
        public string $search = '',
        public bool $caseSensitive = false,
        public int $limit = 1000,
    ) {
        if ($start->greaterThanOrEqualTo($end) || $start->diffInDays($end) > 7) {
            throw new LokiException('Choisissez une période valide de sept jours maximum, avec une fin après le début.');
        }

        if (mb_strlen($search) > 500) {
            throw new LokiException('La recherche est limitée à 500 caractères.');
        }
    }

    public function selector(): string
    {
        $selector = trim((string) config('loki.selector'));

        if (! str_starts_with($selector, '{') || ! str_ends_with($selector, '}')) {
            throw new LokiException('LOKI_SELECTOR doit contenir un sélecteur de flux LogQL, par exemple {project=~".+"}.');
        }

        $matchers = [];

        foreach ($this->labels as $name => $value) {
            self::validateLabel($name);
            $matchers[] = $name.'='.self::quote($value);
        }

        $base = trim(substr($selector, 1, -1));

        return '{'.implode(',', array_filter([$base, ...$matchers])).'}';
    }

    public function expression(): string
    {
        $expression = $this->selector();

        if ($this->search === '') {
            return $expression;
        }

        if ($this->caseSensitive) {
            return $expression.' |= '.self::quote($this->search);
        }

        // Escape RE2 metacharacters: a user search is always literal, never executable LogQL.
        $literal = implode('', array_map(
            fn (string $char): string => str_contains('\\.^$|?*+()[]{}', $char) ? '\\'.$char : $char,
            mb_str_split($this->search),
        ));

        return $expression.' |~ '.self::quote('(?i)'.$literal);
    }

    public function bounds(): array
    {
        return ['start' => $this->start->format('Uu').'000', 'end' => $this->end->format('Uu').'000'];
    }

    public function parameters(): array
    {
        return [
            ...$this->bounds(),
            'query' => $this->expression(),
            'direction' => 'backward',
            'limit' => max(1, min($this->limit, (int) config('loki.max_entries'))),
        ];
    }

    public static function validateLabel(string $name): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
            throw new LokiException('Le nom du label Loki est invalide.');
        }
    }

    private static function quote(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
