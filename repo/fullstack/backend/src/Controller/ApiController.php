<?php

namespace App\Controller;

use App\Service\ApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class ApiController extends AbstractController
{
    protected function ok(array $data = [], int $statusCode = 200): JsonResponse
    {
        return $this->json($data, $statusCode);
    }

    protected function error(string $message, int $code, array $details = []): JsonResponse
    {
        return $this->json([
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], $code);
    }

    protected function validationError(ConstraintViolationListInterface $violations): JsonResponse
    {
        $details = [];
        foreach ($violations as $violation) {
            $details[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return $this->error('Validation failed', 400, $details);
    }

    protected function fromApiException(ApiException $exception): JsonResponse
    {
        return $this->error($exception->getMessage(), $exception->getHttpCode(), $exception->getDetails());
    }
}
