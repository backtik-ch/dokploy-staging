<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Infrastructure\ApiAccess;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeApiToken extends Command
{
    protected $signature = 'make:api-token';

    protected $description = 'Crée un token API en choisissant chaque droit avec des questions oui/non';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Cette commande est interactive. Utilisez infra:token pour une exécution sans interaction.');

            return self::FAILURE;
        }
        $user = $this->selectUser();
        if (! $user) {
            return self::FAILURE;
        }
        $name = $this->askName();
        $days = $this->askLifetime();
        $abilities = $this->askPermissions();
        if ($abilities === []) {
            $this->error('Aucun droit sélectionné. Aucun token n’a été créé.');

            return self::FAILURE;
        }

        $token = $user->createToken($name, $abilities, now()->addDays($days));
        $this->info('Token créé. Copiez sa valeur maintenant : elle ne sera plus affichée.');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }

    private function selectUser(): ?User
    {
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);
        if ($users->isEmpty()) {
            $this->error('Aucun compte existant. Créez un utilisateur avant de générer un token.');

            return null;
        }
        $id = select(
            label: 'Pour quel compte ?',
            options: $users->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' <'.$user->email.'>'])->all(),
        );

        return User::findOrFail($id);
    }

    private function askName(): string
    {
        return text(
            label: 'Nom du token',
            default: 'backtik-infra',
            required: true,
            validate: ['required', 'string', 'max:255'],
        );
    }

    private function askLifetime(): int
    {
        return (int) text(
            label: 'Durée de validité (en jours)',
            default: '30',
            required: true,
            validate: ['required', 'integer', 'between:1,365'],
        );
    }

    private function askPermissions(): array
    {
        $abilities = [];
        foreach (ApiAccess::ABILITIES as $ability) {
            if (confirm(
                label: 'Autoriser '.$ability.' ?',
                default: false,
                yes: 'Oui',
                no: 'Non',
                hint: ApiAccess::ABILITY_DESCRIPTIONS[$ability],
            )) {
                $abilities[] = $ability;
            }
        }

        return $abilities;
    }
}
