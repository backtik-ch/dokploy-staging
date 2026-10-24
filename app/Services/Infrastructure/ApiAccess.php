<?php

namespace App\Services\Infrastructure;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ApiAccess
{
    public const ABILITIES = [
        'infra:read', 'infra:write', 'stagings:deploy', 'stagings:delete',
        'maintenance:write', 'logs:read', 'tokens:manage', 'secrets:write',
        'dependencies:write',
    ];

    public const ABILITY_DESCRIPTIONS = [
        'infra:read' => 'Lire les projets, connexions, réglages, stagings, branches et dépendances.',
        'infra:write' => 'Créer, modifier et archiver les projets et connexions, modifier leurs réglages.',
        'stagings:deploy' => 'Créer, modifier et redéployer les stagings.',
        'stagings:delete' => 'Supprimer les stagings, leurs environnements et leurs volumes.',
        'maintenance:write' => 'Configurer, activer et désactiver la maintenance.',
        'logs:read' => 'Consulter les logs Loki et leurs labels.',
        'tokens:manage' => 'Créer, modifier, renouveler et révoquer les tokens du compte.',
        'secrets:write' => 'Modifier les tokens Dokploy/GitHub et les variables d’environnement des projets.',
        'dependencies:write' => 'Envoyer des rapports de dépendances.',
    ];

    public static function allows(?User $user, string $ability): bool
    {
        return $user?->currentAccessToken() && $user->tokenCan($ability);
    }

    public static function authorize(?User $user, string $ability): void
    {
        if (! self::allows($user, $ability)) {
            throw new AuthorizationException('Permission requise : '.$ability.'.');
        }
    }
}
