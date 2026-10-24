<?php

namespace App\Mcp;

use App\Models\User;
use App\Services\Infrastructure\ApiAccess;
use App\Services\Infrastructure\Operations;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class InfrastructureTool extends Tool
{
    protected const REQUEST = '';

    abstract protected function resource(array $data, User $user): JsonResource;

    public function name(): string
    {
        $requestClass = static::REQUEST;

        return $requestClass::OPERATION;
    }

    public function description(): string
    {
        return Operations::all()[$this->name()]['description'];
    }

    public function shouldRegister(): bool
    {
        $requestClass = static::REQUEST;

        return ApiAccess::allows(auth()->user(), $requestClass::ABILITY);
    }

    public function toArray(): array
    {
        $definition = Operations::all()[$this->name()];
        $readOnly = $definition['method'] === 'GET';

        return [
            'name' => $this->name(), 'description' => $this->description(),
            'inputSchema' => Operations::schema($definition),
            'annotations' => [
                'readOnlyHint' => $readOnly, 'destructiveHint' => ! $readOnly,
                'idempotentHint' => $readOnly, 'openWorldHint' => true,
            ],
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            $requestClass = static::REQUEST;
            $data = $requestClass::validateInput($request->all(), $request->user());
            $resource = $this->resource($data, $request->user());
            $this->preparePagination($resource, $data);

            return Response::structured($resource->response()->getData(true));
        } catch (ValidationException $e) {
            return $this->error(422, 'Validation échouée.', $e->errors());
        } catch (AuthorizationException) {
            return $this->error(403, 'Permission insuffisante.');
        } catch (ModelNotFoundException) {
            return $this->error(404, 'Ressource introuvable.');
        } catch (HttpExceptionInterface $e) {
            return $this->error($e->getStatusCode(), $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->error(500, 'Erreur interne. Consultez les logs du serveur.');
        }
    }

    private function preparePagination(JsonResource $resource, array $data): void
    {
        if (! $resource instanceof ResourceCollection || ! $resource->resource instanceof LengthAwarePaginator) {
            return;
        }
        $path = Operations::all()[$this->name()]['path'];
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        foreach ($matches[1] as $parameter) {
            $path = str_replace('{'.$parameter.'}', rawurlencode((string) $data[$parameter]), $path);
        }
        $resource->resource->setPath(url('/api/v1'.$path))->appends(Arr::except($data, ['page', ...$matches[1]]));
    }

    private function error(int $status, string $message, array $errors = []): Response
    {
        return Response::error(json_encode(compact('status', 'message', 'errors'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
