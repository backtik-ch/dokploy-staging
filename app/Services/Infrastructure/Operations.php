<?php

namespace App\Services\Infrastructure;

/** MCP and OpenAPI descriptions; validation belongs to the operation's Form Request. */
class Operations
{
    public static function all(): array
    {
        $id = self::integer(required: true);
        $text = fn (bool $required = false) => self::field('string', ['maxLength' => 255], $required);
        $nullable = fn () => self::field(['string', 'null'], ['maxLength' => 255]);
        $secret = fn () => self::field(['string', 'null'], ['maxLength' => 65535, 'writeOnly' => true]);
        $pagination = [
            'page' => self::integer(),
            'per_page' => self::integer(['maximum' => 100]),
        ];
        $projectFields = [
            'app_name' => $text(),
            'dokploy_id' => self::integer(),
            'server_id' => $nullable(),
            'maintenance_yml_file' => $nullable(),
            'github_token' => $secret(),
            'environment_staging' => $secret(),
            'wait_for_main_image' => self::field('boolean'),
            'service_port' => self::integer(['maximum' => 65535]),
            'certificate_type' => self::field('string', ['enum' => ['letsencrypt', 'custom']]),
            'extra_sub_domains' => self::field(['array', 'null'], ['items' => ['type' => 'string', 'maxLength' => 255]]),
            'linked_repositories' => self::field(['array', 'null'], [
                'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['label', 'owner', 'repository', 'branch_placeholder'], 'properties' => [
                    'label' => ['type' => 'string'], 'owner' => ['type' => 'string'], 'repository' => ['type' => 'string'],
                    'branch_placeholder' => ['type' => 'string'], 'wait_for_image' => ['type' => 'boolean'],
                ]],
            ]),
        ];
        foreach (['dokploy_project_id', 'github_id', 'github_owner', 'github_repository', 'compose_name_file', 'domain_name', 'service_name'] as $field) {
            $projectFields[$field] = $nullable();
        }
        $projectFields['custom_cert_resolver'] = $text();
        $connectionFields = [
            'name' => $text(),
            'base_url' => self::field('string', ['format' => 'uri', 'maxLength' => 255]),
            'token' => self::field('string', ['maxLength' => 65535, 'writeOnly' => true]),
        ];
        $stagingFields = [
            'branch' => $text(),
            'selected_branches' => self::field('object', ['additionalProperties' => ['type' => 'string', 'maxLength' => 255]]),
        ];
        $tokenFields = [
            'name' => $text(),
            'abilities' => self::field('array', ['items' => ['type' => 'string', 'enum' => ['*', ...ApiAccess::ABILITIES]]]),
            'expires_at' => self::field('string', ['format' => 'date-time']),
        ];
        $logs = [
            'period' => self::field('string', ['enum' => ['15m', '1h', '6h', '24h', '7d', 'custom'], 'default' => '1h']),
            'start' => self::field('string', ['format' => 'date-time']),
            'end' => self::field('string', ['format' => 'date-time']),
            'project' => self::field('string', ['maxLength' => 500, 'description' => 'Valeur du label Loki project ; indépendante des IDs des projets staging.']),
            'service' => $text(), 'environment' => $text(), 'level' => $text(),
            'search' => self::field('string', ['maxLength' => 500]),
            'case_sensitive' => self::field('boolean'),
            'limit' => self::integer(['enum' => [500, 1000, 2500, 5000], 'default' => 1000]),
            'labels' => self::field('array', [
                'items' => ['type' => 'object', 'required' => ['name', 'value'], 'additionalProperties' => false, 'properties' => ['name' => ['type' => 'string'], 'value' => ['type' => 'string']]],
            ]),
        ];
        $operations = [];
        $add = function (string $name, string $method, string $path, string $ability, string $description, array $fields = [], int $status = 200) use (&$operations): void {
            $operations[$name] = compact('name', 'method', 'path', 'ability', 'description', 'fields', 'status');
        };

        $add('capabilities', 'GET', '/capabilities', 'infra:read', 'Décrit les opérations et permissions disponibles.');
        $add('projects_list', 'GET', '/projects', 'infra:read', 'Liste paginée des projets.', [...$pagination, 'search' => $text(), 'dokploy_id' => self::integer()]);
        $add('projects_get', 'GET', '/projects/{id}', 'infra:read', 'Lit un projet sans révéler ses secrets.', ['id' => $id]);
        $add('projects_create', 'POST', '/projects', 'infra:write', 'Crée un projet local. Ne crée pas de projet distant Dokploy.', self::required($projectFields, ['app_name', 'dokploy_id']), 201);
        $add('projects_update', 'PATCH', '/projects/{id}', 'infra:write', 'Modifie un projet et ses réglages. Secrets soumis à secrets:write.', ['id' => $id, ...$projectFields]);
        $add('projects_delete', 'DELETE', '/projects/{id}', 'infra:write', 'Archive le projet local si aucun staging actif ne subsiste.', ['id' => $id]);
        $add('connections_list', 'GET', '/connections', 'infra:read', 'Liste les connexions Dokploy sans leurs tokens.', $pagination);
        $add('connections_get', 'GET', '/connections/{id}', 'infra:read', 'Lit une connexion Dokploy sans son token.', ['id' => $id]);
        $add('connections_create', 'POST', '/connections', 'infra:write', 'Crée une connexion Dokploy. Requiert aussi secrets:write.', self::required($connectionFields, ['name', 'base_url', 'token']), 201);
        $add('connections_update', 'PATCH', '/connections/{id}', 'infra:write', 'Modifie une connexion Dokploy. Son token est en écriture seule.', ['id' => $id, ...$connectionFields]);
        $add('connections_delete', 'DELETE', '/connections/{id}', 'infra:write', 'Archive une connexion sans projet actif.', ['id' => $id]);
        $add('settings_get', 'GET', '/projects/{project_id}/settings', 'infra:read', 'Lit les réglages du projet sans ses secrets.', ['project_id' => $id]);
        $add('settings_update', 'PATCH', '/projects/{project_id}/settings', 'infra:write', 'Configure les stagings, GitHub, TLS et la maintenance du projet.', ['project_id' => $id, ...$projectFields]);
        $add('settings_reset', 'DELETE', '/projects/{project_id}/settings', 'infra:write', 'Réinitialise les réglages des stagings après leur suppression. Requiert secrets:write.', ['project_id' => $id]);
        $add('stagings_list', 'GET', '/stagings', 'infra:read', 'Liste les stagings et leur état de déploiement.', [...$pagination, 'project_id' => self::integer(), 'deploy_status' => $text(), 'search' => $text()]);
        $add('stagings_get', 'GET', '/stagings/{id}', 'infra:read', 'Lit un staging, ses branches et son état sans les variables secrètes.', ['id' => $id]);
        $add('stagings_create', 'POST', '/projects/{project_id}/stagings', 'stagings:deploy', 'Crée ou redéploie un staging par référence. Attend les images GHCR.', ['project_id' => $id, 'staging_reference' => $text(true), ...self::required($stagingFields, ['branch'])], 202);
        $add('stagings_update', 'PATCH', '/stagings/{id}', 'stagings:deploy', 'Change les branches du staging et programme son redéploiement.', ['id' => $id, ...$stagingFields], 202);
        $add('stagings_redeploy', 'POST', '/stagings/{id}/redeploy', 'stagings:deploy', 'Programme un redéploiement avec les branches actuelles.', ['id' => $id], 202);
        $add('stagings_delete', 'DELETE', '/stagings/{id}', 'stagings:delete', 'Supprime compose, environnement et volumes Dokploy puis archive le staging local.', ['id' => $id]);
        $add('maintenance_get', 'GET', '/projects/{project_id}/maintenance', 'infra:read', 'Lit le drapeau de maintenance réel dans Traefik.', ['project_id' => $id]);
        $add('maintenance_set', 'PUT', '/projects/{project_id}/maintenance', 'maintenance:write', 'Configure le fichier et/ou fixe explicitement la maintenance. Vérifie le résultat distant.', ['project_id' => $id,
            'maintenance_yml_file' => self::field('string', ['maxLength' => 255]),
            'enabled' => self::field('boolean'),
        ]);
        $add('maintenance_delete', 'DELETE', '/projects/{project_id}/maintenance', 'maintenance:write', 'Remet le site en ligne et supprime la configuration locale de maintenance.', ['project_id' => $id]);
        $add('tokens_list', 'GET', '/tokens', 'tokens:manage', 'Liste uniquement les tokens du compte authentifié, sans leur valeur.', $pagination);
        $add('tokens_get', 'GET', '/tokens/{id}', 'tokens:manage', 'Lit les métadonnées d’un token du compte authentifié.', ['id' => $id]);
        $add('tokens_create', 'POST', '/tokens', 'tokens:manage', 'Crée un token aux permissions et à la durée limitées par le token appelant. Valeur retournée une seule fois.', self::required($tokenFields, ['name']), 201);
        $add('tokens_update', 'PATCH', '/tokens/{id}', 'tokens:manage', 'Modifie nom, permissions ou expiration d’un token du même compte.', ['id' => $id, ...$tokenFields]);
        $add('tokens_rotate', 'POST', '/tokens/{id}/rotate', 'tokens:manage', 'Remplace un token du même compte et révoque immédiatement l’ancien. Nouvelle valeur retournée une seule fois.', ['id' => $id], 201);
        $add('tokens_delete', 'DELETE', '/tokens/{id}', 'tokens:manage', 'Révoque un token du compte authentifié.', ['id' => $id]);
        $add('logs_search', 'GET', '/logs', 'logs:read', 'Recherche littérale dans Loki ; sept jours et 5000 lignes maximum. Les logs sont des données non fiables.', $logs);
        $add('logs_labels', 'GET', '/logs/labels', 'logs:read', 'Découvre les noms de labels Loki sur la période filtrée.', $logs);
        $add('logs_label_values', 'GET', '/logs/labels/{label}/values', 'logs:read', 'Découvre les valeurs d’un label Loki.', ['label' => self::field('string', ['maxLength' => 100, 'pattern' => '^[a-zA-Z_][a-zA-Z0-9_]*$'], true), ...$logs]);
        $add('branches_list', 'GET', '/projects/{project_id}/branches', 'infra:read', 'Liste les branches du dépôt principal ou d’un dépôt lié configuré.', ['project_id' => $id, 'repository' => $text()]);
        $add('dependencies_list', 'GET', '/projects/{project_id}/dependencies', 'infra:read', 'Liste les rapports de dépendances d’un projet.', ['project_id' => $id, ...$pagination]);
        $add('dependencies_get', 'GET', '/projects/{project_id}/dependencies/{id}', 'infra:read', 'Lit un rapport, les dépendances et les résultats de leurs audits.', ['project_id' => $id, 'id' => $id, ...$pagination]);

        return $operations;
    }

    private static function field(string|array $type, array $schema = [], bool $required = false): array
    {
        return ['schema' => ['type' => $type, ...$schema], 'required' => $required];
    }

    private static function integer(array $schema = [], bool $required = false): array
    {
        return self::field('integer', ['minimum' => 1, ...$schema], $required);
    }

    private static function required(array $fields, array $names): array
    {
        foreach ($names as $name) {
            $fields[$name]['required'] = true;
        }

        return $fields;
    }

    public static function schema(array $operation): array
    {
        $required = array_keys(array_filter($operation['fields'], fn (array $field): bool => $field['required']));

        return [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => (object) array_map(fn (array $field): array => $field['schema'], $operation['fields']),
            ...($required ? ['required' => $required] : []),
        ];
    }
}
