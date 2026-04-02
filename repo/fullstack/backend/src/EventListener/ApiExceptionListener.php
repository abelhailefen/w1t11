<?php

namespace App\EventListener;

use App\Service\ApiException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        if ($exception instanceof ApiException) {
            $event->setResponse(new JsonResponse([
                'code' => $exception->getHttpCode(),
                'message' => $exception->getMessage(),
                'details' => $exception->getDetails(),
            ], $exception->getHttpCode()));
            return;
        }

        if ($exception instanceof AuthenticationException) {
            $event->setResponse(new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized',
                'details' => [],
            ], 401));
            return;
        }

        if ($exception instanceof AccessDeniedException) {
            $event->setResponse(new JsonResponse([
                'code' => 403,
                'message' => 'Forbidden',
                'details' => [],
            ], 403));
        }
    }
}
