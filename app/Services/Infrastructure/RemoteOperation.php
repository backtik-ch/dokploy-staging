<?php

namespace App\Services\Infrastructure;

use App\Exceptions\LokiException;
use App\Exceptions\MaintenanceException;
use App\Exceptions\StagingDeletionException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RemoteOperation
{
    /** Keep remote response bodies and credentials out of API errors. */
    public function run(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException|HttpException $exception) {
            throw $exception;
        } catch (MaintenanceException|StagingDeletionException|LokiException $exception) {
            throw new HttpException(502, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            throw new HttpException(502, 'Le service distant a échoué. Consultez les logs du serveur avant de réessayer.');
        }
    }
}
