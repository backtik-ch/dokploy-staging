<?php

use App\Filament\Resources\Dokploys\Pages\ListDokploys;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ManageProjectStagings;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\Dokploy;
use App\Models\Project;
use App\Models\User;
use App\Services\DeployService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    $this->remoteContent = '{{ $maintenance_enabled := 0 }}'."\nhttp: {}\n";
    $this->remoteStatus = 200;
    Http::fake(function (Request $request) {
        if ($this->remoteStatus !== 200) {
            return Http::response([], $this->remoteStatus);
        }
        if ($request->method() === 'POST') {
            $this->remoteContent = $request['traefikConfig'];

            return Http::response([]);
        }

        return Http::response(json_encode($this->remoteContent));
    });
    $this->dokploy = Dokploy::create([
        'name' => 'Production · équipe',
        'base_url' => 'https://dokploy.example.test',
        'token' => 'never-display-this-secret',
    ]);
    $this->project = Project::create([
        'app_name' => 'Mon projet',
        'dokploy_id' => $this->dokploy->id,
        'maintenance_yml_file' => 'site.yml',
    ])->fresh();
});

it('keeps the same project title and header actions on every tab and reads the live state', function (string $page, bool $enabled): void {
    $this->remoteContent = str_replace(':= 0', ':= '.(int) $enabled, $this->remoteContent);
    // Start with the opposite cached value to prove the page reads the actual file.
    $this->project->update(['maintenance_enabled' => ! $enabled]);
    $component = Livewire::test($page, ['record' => $this->project->id])
        ->assertSuccessful()
        ->assertActionHasLabel('toggleMaintenance', $enabled ? 'Remettre en ligne' : 'Mettre en maintenance')
        ->assertActionEnabled('toggleMaintenance')
        ->assertActionVisible('createStaging')
        ->assertActionDoesNotExist('enableMaintenance')
        ->assertActionDoesNotExist('disableMaintenance')
        ->assertActionDoesNotExist('refreshMaintenance');
    expect($component->instance()->getTitle())->toBe('Mon projet')
        ->and($this->project->fresh()->maintenance_enabled)->toBe($enabled);
    Http::assertSentCount(1);
})->with([ViewProject::class, EditProject::class, ManageProjectStagings::class])->with([false, true]);

it('uses the same cached status across tabs for three minutes and refreshes after expiry', function (): void {
    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertActionHasLabel('toggleMaintenance', 'Mettre en maintenance');
    $this->remoteContent = str_replace(':= 0', ':= 1', $this->remoteContent);
    Livewire::test(EditProject::class, ['record' => $this->project->id])
        ->assertActionHasLabel('toggleMaintenance', 'Mettre en maintenance');
    Livewire::test(ManageProjectStagings::class, ['record' => $this->project->id])
        ->assertActionHasLabel('toggleMaintenance', 'Mettre en maintenance');
    Http::assertSentCount(1);
    $this->travel(181)->seconds();
    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertActionHasLabel('toggleMaintenance', 'Remettre en ligne');
    Http::assertSentCount(2);
});

it('toggles maintenance and immediately updates the same button', function (string $page): void {
    Livewire::test($page, ['record' => $this->project->id])
        ->callAction('toggleMaintenance')->assertNotified('Maintenance activée et vérifiée')
        ->assertActionHasLabel('toggleMaintenance', 'Remettre en ligne')
        ->callAction('toggleMaintenance')->assertNotified('Maintenance désactivée et vérifiée')
        ->assertActionHasLabel('toggleMaintenance', 'Mettre en maintenance');
    expect($this->project->fresh()->maintenance_enabled)->toBeFalse();
    Http::assertSentCount(7);
})->with([ViewProject::class, EditProject::class, ManageProjectStagings::class]);

it('disables the toggle when the initial status cannot be read', function (): void {
    $this->remoteStatus = 403;
    $this->project->update(['maintenance_enabled' => false]);
    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertNotified('Statut de maintenance indisponible')
        ->assertActionDisabled('toggleMaintenance')->assertSee('À vérifier')->assertSee('HTTP 403');
    expect($this->project->fresh()->maintenance_enabled)->toBeNull();
    Http::assertSentCount(1);
});

it('shows an error and disables the toggle when a subsequent operation fails', function (): void {
    $component = Livewire::test(ViewProject::class, ['record' => $this->project->id]);
    $this->remoteStatus = 403;
    $component->callAction('toggleMaintenance')->assertNotified('Maintenance non confirmée')
        ->assertActionDisabled('toggleMaintenance');
});

it('creates projects without requiring staging configuration upfront', function (): void {
    Livewire::test(CreateProject::class)
        ->fillForm(['app_name' => 'Nouveau site', 'dokploy_id' => $this->dokploy->id, 'maintenance_yml_file' => 'nouveau.yml'])
        ->call('create')->assertHasNoFormErrors();
    $project = Project::where('app_name', 'Nouveau site')->sole();
    expect($project->maintenance_yml_file)->toBe('nouveau.yml')->and($project->github_id)->toBeNull();
});

it('opens the create staging modal on every tab and queues its deployment', function (string $page): void {
    $this->project->update([
        'dokploy_project_id' => 'project-id', 'github_id' => 'integration-id',
        'github_owner' => 'backtik', 'github_repository' => 'site',
        'compose_name_file' => 'compose.yml', 'domain_name' => 'staging.example.test',
    ]);
    $this->project->update(['linked_repositories' => [[
        'label' => 'Frontend', 'owner' => 'backtik', 'repository' => 'frontend', 'branch_placeholder' => 'FRONTEND_BRANCH',
    ]]]);
    $this->mock(\App\Services\GithubService::class, function ($mock): void {
        $mock->shouldReceive('warmProjectBranchesCache')->once();
        $mock->shouldReceive('listBranches')->andReturn(['main' => 'main', 'feature' => 'feature']);
    });
    $this->mock(DeployService::class, function ($mock): void {
        $mock->shouldReceive('deploy')->once()->withArgs(fn (Project $project, $action, $reference, $branch, $selected, $deferred): bool => $project->is($this->project) && $action === 'create' && $reference === 'preview-42'
            && $branch === 'main' && $selected === ['FRONTEND_BRANCH' => 'feature'] && $deferred === true
        )->andReturn(new \App\Models\Staging);
    });
    Livewire::test($page, ['record' => $this->project->id])
        ->assertActionEnabled('createStaging')
        ->assertActionExists('createStaging', fn (\Filament\Actions\Action $action): bool => $action->getUrl() === null)
        ->callAction('createStaging', ['staging_reference' => 'preview-42', 'selected_branches' => ['FRONTEND_BRANCH' => 'feature']])
        ->assertHasNoActionErrors()->assertNotified('Staging créé');
})->with([ViewProject::class, EditProject::class, ManageProjectStagings::class]);

it('validates maintenance file paths in the form', function (string $file): void {
    Livewire::test(EditProject::class, ['record' => $this->project->id])
        ->fillForm(['maintenance_yml_file' => $file])->call('save')->assertHasFormErrors(['maintenance_yml_file']);
    expect($this->project->fresh()->maintenance_yml_file)->toBe('site.yml');
})->with(['../site.yml', 'site.txt', "file\n.yml"]);

it('allows removing maintenance configuration and hides the toggle', function (): void {
    Livewire::test(EditProject::class, ['record' => $this->project->id])
        ->fillForm(['maintenance_yml_file' => ''])->call('save')->assertHasNoFormErrors();
    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertActionHidden('toggleMaintenance')->assertSee('Non configurée');
});

it('does not call Dokploy for project lists and retains their explicit maintenance actions', function (): void {
    Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$this->project]);
    Http::assertNothingSent();
    Livewire::test(ListProjects::class)
        ->callAction(TestAction::make('enableMaintenance')->table($this->project))
        ->assertNotified('Maintenance activée et vérifiée');
    expect($this->project->fresh()->maintenance_enabled)->toBeTrue();
});

it('distinguishes connections sharing an URL and never lists their tokens', function (): void {
    $other = Dokploy::create(['base_url' => $this->dokploy->base_url, 'token' => 'other-secret']);
    expect($this->dokploy->connectionLabel())->toContain('Production · équipe', '#'.$this->dokploy->id)
        ->not->toBe($other->connectionLabel());
    Livewire::test(ListDokploys::class)->assertCanSeeTableRecords([$this->dokploy, $other])
        ->assertDontSee('never-display-this-secret')->assertDontSee('other-secret')->assertSee('Connexion #'.$other->id);
});

it('reports missing staging configuration before making any deployment request', function (): void {
    expect(fn () => app(DeployService::class)->deploy($this->project, 'create', '42', 'main'))
        ->toThrow(ValidationException::class, 'Complétez la configuration');
    Http::assertNothingSent();
});

it('refreshes without cache from the maintenance section and updates the toggle and shared cache', function (): void {
    $component = Livewire::test(ViewProject::class, ['record' => $this->project->id]);
    $this->remoteContent = str_replace(':= 0', ':= 1', $this->remoteContent);
    $component->callAction(TestAction::make('refreshMaintenance')->schemaComponent('site-maintenance', 'infolist'))
        ->assertNotified('Maintenance activée et vérifiée')
        ->assertActionHasLabel('toggleMaintenance', 'Remettre en ligne');
    Livewire::test(EditProject::class, ['record' => $this->project->id])
        ->assertActionHasLabel('toggleMaintenance', 'Remettre en ligne');
    Http::assertSentCount(2);
});

it('can recover from a cached connection error using the manual refresh icon', function (): void {
    $this->remoteStatus = 403;
    $component = Livewire::test(ViewProject::class, ['record' => $this->project->id])->assertActionDisabled('toggleMaintenance');
    Livewire::test(EditProject::class, ['record' => $this->project->id])->assertActionDisabled('toggleMaintenance');
    Http::assertSentCount(1);
    $this->remoteStatus = 200;
    $component->callAction(TestAction::make('refreshMaintenance')->schemaComponent('site-maintenance', 'infolist'))
        ->assertActionEnabled('toggleMaintenance')->assertActionHasLabel('toggleMaintenance', 'Mettre en maintenance');
    Http::assertSentCount(2);
});

it('only notifies about errors when an actual uncached request fails', function (): void {
    $this->remoteStatus = 403;
    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertNotified('Statut de maintenance indisponible');

    foreach ([EditProject::class, ManageProjectStagings::class, ViewProject::class] as $page) {
        Livewire::test($page, ['record' => $this->project->id])
            ->assertActionDisabled('toggleMaintenance')
            ->assertNotNotified('Statut de maintenance indisponible');
    }
    Http::assertSentCount(1);

    $this->travel(181)->seconds();
    $component = Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertNotified('Statut de maintenance indisponible');
    Http::assertSentCount(2);

    $component->callAction(TestAction::make('refreshMaintenance')->schemaComponent('site-maintenance', 'infolist'))
        ->assertNotified('Maintenance non confirmée');
    Http::assertSentCount(3);
});
