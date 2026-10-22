<?php

use App\Exceptions\LokiException;
use App\Filament\Pages\Logs;
use App\Models\User;
use App\Services\Loki\LogEntry;
use App\Services\Loki\LogQuery;
use App\Services\Loki\LogQueryFactory;
use App\Services\Loki\LokiClient;
use App\Services\Loki\LokiService;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['loki.url' => 'http://loki.test:3100', 'loki.selector' => '{project=~".+"}', 'loki.project_label' => 'project']);
    CarbonImmutable::setTestNow('2026-10-22 12:00:00 UTC');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    $this->remoteStatus = 200;
    $this->streams = [
        ['stream' => ['project' => 'vivalti', 'service' => 'api', 'env' => 'production', 'level' => 'error', 'host' => 'main'], 'values' => [
            ['1792670340123456789', '{"level":"error","message":"Database connection failed","context":{"attempt":3}}'],
        ]],
        ['stream' => ['project' => 'impactarti', 'service' => 'web', 'env' => 'staging', 'level' => 'info'], 'values' => [
            ['1792670340123456788', 'GET /health 200'],
            ['1792670280000000000', "Request completed\nsecond line"],
        ]],
    ];
    Http::fake(function (Request $request) {
        if ($this->remoteStatus !== 200) {
            return Http::response('secret remote error <script>alert(1)</script>', $this->remoteStatus);
        }
        if (str_contains($request->url(), '/query_range')) {
            return Http::response(['status' => 'success', 'data' => ['resultType' => 'streams', 'result' => $this->streams]]);
        }
        $values = match (true) {
            str_contains($request->url(), '/label/project/values') => ['vivalti', 'impactarti'],
            str_contains($request->url(), '/label/service/values') => ['api', 'web'],
            str_contains($request->url(), '/label/env/values') => ['production', 'staging'],
            str_contains($request->url(), '/label/level/values') => ['error', 'info'],
            default => ['project', 'service', 'env', 'level', 'host'],
        };

        return Http::response(['status' => 'success', 'data' => $values]);
    });
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('requires authentication and registers the Logs sidebar entry', function (): void {
    $this->get('/logs')->assertRedirect('/login');
    expect(Logs::getNavigationLabel())->toBe('Logs');
});

it('renders the native Filament explorer, loaded statistics and log detail safely', function (): void {
    $this->actingAs(User::factory()->create());
    $component = Livewire::test(Logs::class)->assertSuccessful()
        ->assertSee('Explorer les logs')->assertSee('Database connection failed')->assertSee('GET /health 200');
    $entries = $component->instance()->result->entries;
    expect($entries->pluck('timestamp')->all())->toBe(['1792670340123456789', '1792670340123456788', '1792670280000000000'])
        ->and($component->instance()->result->summary())->toMatchArray(['count' => 3, 'errors' => 1, 'warnings' => 0, 'streams' => 2]);
    $component->mountAction(TestAction::make('details')->table($entries->first()['id']));
    $component->assertActionMounted(TestAction::make('details')->table($entries->first()['id']));
    expect($component->getMountedActionModalHtml())->toContain('Détail du log', 'context', 'attempt');
});

it('sends body search and combined project and arbitrary labels to Loki before the limit', function (): void {
    $this->actingAs(User::factory()->create());
    Livewire::test(Logs::class)
        ->set('filters.project', 'vivalti')
        ->set('filters.labels', [['name' => 'host', 'value' => 'main']])
        ->set('filters.level', 'error')
        ->searchTable('Database [failed].*')
        ->assertSuccessful();
    $last = Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/query_range'))->last()[0];
    expect($last['query'])->toBe((new LogQuery(CarbonImmutable::now()->subHour(), CarbonImmutable::now(), ['project' => 'vivalti', 'level' => 'error', 'host' => 'main'], 'Database [failed].*'))->expression());
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/query_range')
        && str_contains($request['query'], 'Database')
        && $request['limit'] === 1000 && $request['direction'] === 'backward');
});

it('uses literal escaping for LogQL values and supports case sensitive searches', function (): void {
    $query = new LogQuery(CarbonImmutable::now()->subHour(), CarbonImmutable::now(), ['project' => 'foo"} |= "secret'], 'a"b\\c', true);
    expect($query->expression())->toBe('{project=~".+",project="foo\\"} |= \\"secret"} |= "a\\"b\\\\c"');
    expect(fn () => new LogQuery(CarbonImmutable::now(), CarbonImmutable::now()->subHour()))->toThrow(LokiException::class);
    expect(fn () => (new LogQuery(CarbonImmutable::now()->subHour(), CarbonImmutable::now(), ['a} |=' => 'bad']))->expression())->toThrow(LokiException::class);
});

it('discovers sorted labels scoped to the selected project and caches requests', function (): void {
    $query = new LogQuery(CarbonImmutable::now()->subHour(), CarbonImmutable::now(), ['project' => 'vivalti']);
    $service = app(LokiService::class);
    expect($service->labelValues('service', $query))->toBe(['api' => 'api', 'web' => 'web']);
    $service->labelValues('service', $query);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['query'] === '{project=~".+",project="vivalti"}');
});

it('exports complete raw lines and labels as NDJSON', function (): void {
    $this->actingAs(User::factory()->create());
    $component = Livewire::test(Logs::class)->call('export')->assertFileDownloaded('logs-20261022-120000.ndjson');
    $download = $component->instance()->export();
    ob_start();
    $download->sendContent();
    $content = ob_get_clean();
    $lines = array_map(fn ($line) => json_decode($line, true), explode("\n", trim($content)));
    expect($lines)->toHaveCount(3)->and($lines[0]['labels']['project'])->toBe('vivalti')
        ->and($lines[0]['line'])->toContain('context')->and($lines[0]['timestamp'])->toBe('1792670340123456789');
});

it('shows actionable missing configuration and remote errors without exposing response secrets', function (?string $url, int $status, string $message): void {
    config(['loki.url' => $url]);
    $this->remoteStatus = $status;
    $this->actingAs(User::factory()->create());
    Livewire::test(Logs::class)->assertSuccessful()->assertSee($message)->assertDontSee('secret remote error');
})->with([
    [null, 200, 'Renseignez LOKI_URL'],
    ['http://loki.test:3100', 403, 'Accès à Loki refusé'],
    ['http://loki.test:3100', 503, 'HTTP 503'],
]);

it('normalizes plain text and structured metadata without trusting embedded HTML', function (): void {
    $entry = LogEntry::fromLoki(['project' => 'demo'], ['1792670340123456789', '<script>alert(1)</script> [WARN] message', ['trace_id' => 'abc']]);
    expect($entry['level'])->toBe('warning')->and($entry['metadata'])->toBe(['trace_id' => 'abc'])->and($entry['time'])->toBe('11:59:00.123');
    $this->streams = [['stream' => ['project' => 'demo'], 'values' => [['1792670340123456789', '<script>alert(1)</script>']]]];
    $this->actingAs(User::factory()->create());
    Livewire::test(Logs::class)->assertDontSeeHtml('<script>alert(1)</script>')->assertSee('&lt;script&gt;', false);
});

it('retains nanosecond precision when sorting across streams and reports truncation', function (): void {
    config(['loki.max_entries' => 3]);
    $query = new LogQuery(CarbonImmutable::now()->subHour(), CarbonImmutable::now());
    $result = app(LokiService::class)->search($query);
    expect($result->summary()['limited'])->toBeTrue()->and(array_sum($result->summary()['buckets']))->toBe(3)
        ->and($result->entries->first()['timestamp'])->toBe('1792670340123456789');
});

it('rejects invalid custom ranges without querying Loki', function (): void {
    $this->actingAs(User::factory()->create());
    $component = Livewire::test(Logs::class);
    $queries = fn () => Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/query_range'))->count();
    $before = $queries();
    $component->set('filters.period', 'custom')->set('filters.start', '2026-10-22 12:00:00')->set('filters.end', '2026-10-21 12:00:00')
        ->assertSee('Choisissez une période valide');
    expect($queries())->toBe($before);
});

it('keeps a stable snapshot while paginating and reading details in live mode', function (): void {
    $this->streams[0]['values'] = array_map(fn (int $i): array => [(string) (1792670340123456789 - $i * 1000000000), 'Log '.$i], range(0, 64));
    $this->actingAs(User::factory()->create());
    $component = Livewire::test(Logs::class)->set('live', true)->call('setPage', 2);
    $anchor = $component->get('endAt');
    $this->travel(20)->seconds();
    $component->call('poll')->assertSet('endAt', $anchor);
    $entry = $component->instance()->getTableRecords()->first();
    $component->mountAction(TestAction::make('details')->table($entry['id']))
        ->assertActionMounted(TestAction::make('details')->table($entry['id']));
    expect($component->getMountedActionModalHtml())->toContain($entry['raw']);
    $component->call('poll')->assertSet('endAt', $anchor);
    $component->unmountAction()->call('setPage', 1)->call('poll');
    expect($component->get('endAt'))->not->toBe($anchor);
});

it('isolates the chosen stream and resets filters and body search', function (): void {
    $this->actingAs(User::factory()->create());
    $component = Livewire::test(Logs::class);
    $entry = $component->instance()->getTableRecords()->first();
    $component->callAction(TestAction::make('isolate')->table($entry['id']));
    expect($component->instance()->result->query->labels)->toBe($entry['labels']);
    $component->searchTable('database')->callAction('resetFilters')
        ->assertSet('tableSearch', '')->assertSet('filters.labels', [])->assertSet('filters.project', null);
});

it('rejects contradictory values instead of silently replacing the project filter', function (): void {
    $filters = ['period' => '1h', 'project' => 'vivalti', 'labels' => [['name' => 'project', 'value' => 'impactarti']], 'limit' => 1000];
    expect(fn () => app(LogQueryFactory::class)->make($filters, CarbonImmutable::now()->toIso8601String()))
        ->toThrow(LokiException::class, 'Un même label possède deux valeurs différentes');
    Http::assertNothingSent();
});

it('sends credentials server side and isolates metadata caches by tenant', function (): void {
    config(['loki.username' => 'user', 'loki.password' => 'secret', 'loki.tenant_id' => 'tenant-a']);
    $client = app(LokiClient::class);
    $client->cached('labels', []);
    config(['loki.tenant_id' => 'tenant-b']);
    $client->cached('labels', []);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('user:secret')) && $request->hasHeader('X-Scope-OrgID', 'tenant-a'));
});
