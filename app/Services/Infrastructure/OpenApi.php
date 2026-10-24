<?php

namespace App\Services\Infrastructure;

class OpenApi
{
    public static function document(): array
    {
        $paths = [];
        foreach (Operations::all() as $operation) {
            $schema = Operations::schema($operation);
            $parameters = [];
            $bodyFields = [];
            foreach ($operation['fields'] as $name => $field) {
                $isPath = str_contains($operation['path'], '{'.$name.'}');
                if ($isPath || $operation['method'] === 'GET') {
                    $parameters[] = [
                        'name' => $name, 'in' => $isPath ? 'path' : 'query',
                        'required' => $isPath || in_array($name, $schema['required'] ?? [], true),
                        'schema' => $field['schema'],
                        ...(! $isPath && in_array($field['schema']['type'], ['array', 'object'], true) ? ['style' => 'deepObject', 'explode' => true] : []),
                    ];
                } else {
                    $bodyFields[$name] = $field;
                }
            }
            $bodyOperation = [...$operation, 'fields' => $bodyFields];
            $paths[$operation['path']][strtolower($operation['method'])] = [
                'operationId' => $operation['name'], 'summary' => $operation['description'],
                'tags' => [explode('_', $operation['name'])[0]], 'security' => [['bearerAuth' => []]],
                'x-required-ability' => $operation['ability'],
                'parameters' => $parameters,
                ...($bodyFields ? ['requestBody' => ['required' => ! empty(Operations::schema($bodyOperation)['required']), 'content' => ['application/json' => ['schema' => Operations::schema($bodyOperation)]]]] : []),
                'responses' => [
                    (string) $operation['status'] => ['description' => 'Succès. API Resource {data, links?, meta?}, avec pagination Laravel native pour les listes paginées. Les secrets de configuration sont exclus sauf plain_text_token à la création/rotation.'],
                    '401' => ['description' => 'Token absent, expiré ou révoqué.'],
                    '403' => ['description' => 'Permission insuffisante.'],
                    '404' => ['description' => 'Ressource introuvable.'],
                    '409' => ['description' => 'Conflit avec l’état actuel des ressources.'],
                    '422' => ['description' => 'Validation : {message, errors}.'],
                    '429' => ['description' => 'Limite de requêtes dépassée.'],
                    '502' => ['description' => 'Échec du service distant ; vérifier l’état avant de répéter une mutation.'],
                ],
            ];
        }

        return [
            'openapi' => '3.1.0', 'info' => ['title' => 'Backtik Staging Infrastructure', 'version' => '1.0.0'],
            'servers' => [['url' => url('/api/v1')]],
            'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']]],
            'paths' => $paths,
        ];
    }
}
