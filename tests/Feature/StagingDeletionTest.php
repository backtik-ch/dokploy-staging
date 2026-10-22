<?php

use App\Exceptions\StagingDeletionException;
use App\Filament\Resources\Projects\Pages\ManageProjectStagings;
use App\Models\Dokploy;
use App\Models\Project;
use App\Models\Staging;
use App\Models\User;
use App\Services\StagingDeletionService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

class RestrictedStagingDeletionPolicy extends \App\Policies\StagingPolicy
{
    public function delete(User $user, Staging $staging): bool
    {
        return $staging->pr_number !== 'protected';
    }
}

function stagingDeletionError(string $message, int $status = 404, string $code = 'NOT_FOUND'): array
{
    return [['error' => ['json' => ['message' => $message, 'code' => -32004, 'data' => ['code' => $code, 'httpStatus' => $status]]]]];
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    $dokploy = Dokploy::create(['base_url' => 'https://dokploy.example.test', 'token' => 'private-token']);
    $this->project = Project::create(['app_name' => 'elyops', 'dokploy_id' => $dokploy->id]);
    $this->staging = $this->project->stagings()->create([
        'pr_number' => 'preview-42', 'branch' => 'main', 'compose_id' => 'compose-42',
        'environment_id' => 'environment-42', 'environment' => '',
    ]);
    $this->service = app(StagingDeletionService::class);
});

it('deletes the remote compose and environment before removing the staging locally', function (): void {
    Http::fake(function (Request $request) {
        expect($this->staging->fresh()->trashed())->toBeFalse();

        return Http::response([['result' => ['data' => ['json' => null]]]]);
    });
    expect($this->service->delete($this->staging))->toBeTrue();
    $this->assertSoftDeleted($this->staging);
    Http::assertSentCount(2);
    $requests = Http::recorded()->pluck(0);
    expect($requests[0]->url())->toContain('/compose.delete?batch=1')
        ->and($requests[1]->url())->toContain('/environment.remove?batch=1');
    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'compose.delete')) {
            return false;
        }
        $body = json_decode($request->body(), flags: JSON_THROW_ON_ERROR);

        return $request->hasHeader('x-api-key', 'private-token')
            && $body instanceof stdClass
            && $body->{'0'}->json->composeId === 'compose-42'
            && $body->{'0'}->json->deleteVolumes === true;
    });
});

it('removes local stagings even when the compose or environment is already missing', function (bool $missingCompose, bool $missingEnvironment): void {
    Http::fake(function (Request $request) use ($missingCompose, $missingEnvironment) {
        $compose = str_contains($request->url(), 'compose.delete');

        return ($compose ? $missingCompose : $missingEnvironment)
            ? Http::response(stagingDeletionError($compose ? 'Compose not found' : 'Environment not found'), 404)
            : Http::response([['result' => ['data' => ['json' => true]]]]);
    });
    expect($this->service->delete($this->staging))->toBeTrue();
    $this->assertSoftDeleted($this->staging);
    Http::assertSentCount(2);
})->with([[true, false], [false, true], [true, true]]);

it('recognizes wrapped missing resource errors from older Dokploy versions and batch responses', function (string $endpoint, string $message, int $status, string $code, bool $jsonWrapped): void {
    Http::fake(function (Request $request) use ($endpoint, $message, $status, $code, $jsonWrapped) {
        if (! str_contains($request->url(), $endpoint)) {
            return Http::response([['result' => ['data' => ['json' => true]]]]);
        }
        $body = stagingDeletionError($message, $status, $code);
        if (! $jsonWrapped) {
            $body[0]['error'] = $body[0]['error']['json'];
        }

        return Http::response($body, $status);
    });
    expect($this->service->delete($this->staging))->toBeTrue();
    $this->assertSoftDeleted($this->staging);
})->with([
    'legacy compose 500' => ['compose.delete', 'Compose not found', 500, 'INTERNAL_SERVER_ERROR', true],
    'legacy service 500' => ['compose.delete', 'Service not found', 500, 'INTERNAL_SERVER_ERROR', true],
    'wrapped environment 400' => ['environment.remove', 'Error deleting the environment: Environment not found', 400, 'BAD_REQUEST', true],
    'batch HTTP 207' => ['compose.delete', 'Compose not found', 207, 'NOT_FOUND', true],
    'unwrapped tRPC error' => ['compose.delete', 'Compose not found', 404, 'NOT_FOUND', false],
]);

it('keeps local stagings for permission failures and other errors', function (string $message, int $status, string $code): void {
    Http::fake(['*' => Http::response(stagingDeletionError($message, $status, $code), $status)]);
    expect(fn () => $this->service->delete($this->staging))->toThrow(StagingDeletionException::class);
    expect($this->staging->fresh()->trashed())->toBeFalse();
    Http::assertSentCount(1);
})->with([
    'authentication' => ['Unauthorized', 401, 'UNAUTHORIZED'],
    'authorization despite missing message' => ['Compose not found', 403, 'FORBIDDEN'],
    'authorization in batch' => ['Compose not found', 207, 'FORBIDDEN'],
    'server error' => ['Unable to connect to database', 500, 'INTERNAL_SERVER_ERROR'],
    'missing API procedure' => ['No procedure found on path compose.delete', 404, 'NOT_FOUND'],
    'missing server' => ['Server not found', 404, 'NOT_FOUND'],
    'tRPC error with HTTP 200' => ['Permission denied', 200, 'FORBIDDEN'],
]);

it('does not treat proxy pages or malformed success responses as missing stagings', function (mixed $body, int $status): void {
    Http::fake(['*' => Http::response($body, $status)]);
    expect(fn () => $this->service->delete($this->staging))->toThrow(StagingDeletionException::class);
    expect($this->staging->fresh()->trashed())->toBeFalse();
})->with([['<html>Not Found</html>', 404], ['<html>Proxy error</html>', 200], [[], 200]]);

it('retains the local record after connection failures', function (): void {
    Http::fake(['*' => Http::failedConnection()]);
    expect(fn () => $this->service->delete($this->staging))->toThrow(StagingDeletionException::class, 'Connexion');
    expect($this->staging->fresh()->trashed())->toBeFalse();
});

it('can retry a partial deletion when the compose was deleted but environment cleanup failed', function (): void {
    Http::fake([
        '*compose.delete*' => Http::sequence()->push([['result' => ['data' => ['json' => true]]]])->push(stagingDeletionError('Compose not found'), 404),
        '*environment.remove*' => Http::sequence()->push(stagingDeletionError('Permission denied', 403, 'FORBIDDEN'), 403)->push([['result' => ['data' => ['json' => true]]]]),
    ]);
    expect(fn () => $this->service->delete($this->staging))->toThrow(StagingDeletionException::class);
    expect($this->staging->fresh()->trashed())->toBeFalse();
    expect($this->service->delete($this->staging))->toBeTrue();
    $this->assertSoftDeleted($this->staging);
    Http::assertSentCount(4);
});

it('does not resend deletions for an already deleted local record', function (): void {
    $this->staging->delete();
    expect($this->service->delete($this->staging))->toBeTrue();
    Http::assertNothingSent();
});

it('uses the same idempotent deletion through the API', function (): void {
    Http::fake(function (Request $request) {
        return Http::response(stagingDeletionError(str_contains($request->url(), 'compose.delete') ? 'Compose not found' : 'Environment not found'), 404);
    });
    $this->deleteJson('/api/remove', ['project_id' => $this->project->id, 'staging_reference' => 'preview-42'])->assertSuccessful();
    $this->assertSoftDeleted($this->staging);
    Http::assertSentCount(2);
});

it('labels row deletion Supprimer and removes missing stagings locally', function (): void {
    Http::fake(function (Request $request) {
        return Http::response(stagingDeletionError(str_contains($request->url(), 'compose.delete') ? 'Compose not found' : 'Environment not found'), 404);
    });
    Livewire::test(ManageProjectStagings::class, ['record' => $this->project->id])
        ->assertActionHasLabel(TestAction::make('delete')->table($this->staging), 'Supprimer')
        ->callAction(TestAction::make('delete')->table($this->staging))
        ->assertNotified('Staging supprimé')
        ->assertCanNotSeeTableRecords([$this->staging]);
    $this->assertSoftDeleted($this->staging);
});

it('shows row failures without deleting the local record', function (): void {
    Http::fake(['*' => Http::response(stagingDeletionError('Permission denied', 403, 'FORBIDDEN'), 403)]);
    Livewire::test(ManageProjectStagings::class, ['record' => $this->project->id])
        ->callAction(TestAction::make('delete')->table($this->staging))
        ->assertNotified('Suppression impossible')
        ->assertCanSeeTableRecords([$this->staging]);
    expect($this->staging->fresh()->trashed())->toBeFalse();
});

it('bulk deletes selected existing and missing stagings without affecting unselected rows', function (): void {
    $second = $this->staging->replicate()->fill(['pr_number' => 'preview-43', 'compose_id' => 'compose-43', 'environment_id' => 'environment-43']);
    $second->save();
    $unselected = $this->staging->replicate()->fill(['pr_number' => 'unselected']);
    $unselected->save();
    Http::fake(function (Request $request) {
        $compose = str_contains($request->url(), 'compose.delete');
        $missing = ($request->data()['0']['json'][$compose ? 'composeId' : 'environmentId'] ?? '') === ($compose ? 'compose-43' : 'environment-43');

        return $missing
            ? Http::response(stagingDeletionError($compose ? 'Compose not found' : 'Environment not found'), 404)
            : Http::response([['result' => ['data' => ['json' => true]]]]);
    });
    Livewire::test(ManageProjectStagings::class, ['record' => $this->project->id])
        ->selectTableRecords([$this->staging, $second])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotified('Stagings supprimés')
        ->assertCanNotSeeTableRecords([$this->staging, $second])
        ->assertCanSeeTableRecords([$unselected]);
    $this->assertSoftDeleted($this->staging);
    $this->assertSoftDeleted($second);
    expect($unselected->fresh()->trashed())->toBeFalse();
    Http::assertSentCount(4);
});

it('continues bulk deletion after a genuine failure and retains failed records', function (): void {
    $second = $this->staging->replicate()->fill(['pr_number' => 'preview-43', 'compose_id' => 'compose-43']);
    $second->save();
    Http::fake(function (Request $request) {
        return ($request->data()['0']['json']['composeId'] ?? '') === 'compose-42'
            ? Http::response(stagingDeletionError('Permission denied', 403, 'FORBIDDEN'), 403)
            : Http::response([['result' => ['data' => ['json' => true]]]]);
    });
    Livewire::test(ManageProjectStagings::class, ['record' => $this->project->id])
        ->selectTableRecords([$this->staging, $second])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotNotified('Stagings supprimés')
        ->assertCanSeeTableRecords([$this->staging])->assertCanNotSeeTableRecords([$second]);
    expect($this->staging->fresh()->trashed())->toBeFalse();
    $this->assertSoftDeleted($second);
    Http::assertSentCount(3);
});

it('checks delete authorization for each selected record', function (): void {
    $second = $this->staging->replicate()->fill(['pr_number' => 'protected']);
    $second->save();
    Gate::policy(Staging::class, RestrictedStagingDeletionPolicy::class);
    Http::fake(['*' => Http::response([['result' => ['data' => ['json' => true]]]])]);
    Livewire::test(ManageProjectStagings::class, ['record' => $this->project->id])
        ->selectTableRecords([$this->staging, $second])
        ->callAction(TestAction::make('delete')->table()->bulk());
    $this->assertSoftDeleted($this->staging);
    expect($second->fresh()->trashed())->toBeFalse();
    Http::assertSentCount(2);
});
