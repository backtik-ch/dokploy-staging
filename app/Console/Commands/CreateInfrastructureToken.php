<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Infrastructure\ApiAccess;
use Illuminate\Console\Command;

class CreateInfrastructureToken extends Command
{
    protected $signature = 'infra:token {user : Email du compte existant}
        {--name=backtik-infra : Nom du token}
        {--ability=* : Permission (option répétable ; lecture seule par défaut)}
        {--days=30 : Validité en jours (1 à 365)}';

    protected $description = 'Crée un token pour l’API et le MCP infrastructure (valeur affichée une seule fois)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('user'))->first();
        if (! $user) {
            $this->error('Compte introuvable.');

            return self::FAILURE;
        }
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
        $abilities = $this->option('ability') ?: ['infra:read'];
        if ($days === false || array_diff($abilities, ['*', ...ApiAccess::ABILITIES]) || blank($this->option('name'))) {
            $this->error('Durée, nom ou permissions invalides. Permissions : '.implode(', ', ApiAccess::ABILITIES).', *');

            return self::FAILURE;
        }
        $token = $user->createToken($this->option('name'), array_values(array_unique($abilities)), now()->addDays($days));
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
