<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CsrfProtectionListener implements EventSubscriberInterface
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
            KernelEvents::RESPONSE => ['onKernelResponse', -8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->requiresCsrfValidation($request->getMethod(), $request->getPathInfo())) {
            return;
        }

        if (!$this->isAuthenticatedRequest($request)) {
            return;
        }

        $cookieToken = (string) $request->cookies->get('XSRF-TOKEN', '');
        $headerToken = (string) $request->headers->get('X-XSRF-TOKEN', '');
        if ($headerToken === '' || !hash_equals($cookieToken, $headerToken)) {
            $event->setResponse(new JsonResponse([
                'code' => 403,
                'message' => 'CSRF token mismatch',
            ], 403));
        }
    }

    private function isAuthenticatedRequest(Request $request): bool
    {
        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() instanceof User) {
            return true;
        }

        $authHeader = (string) $request->headers->get('Authorization', '');
        return str_starts_with($authHeader, 'Bearer ');
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token || !($token->getUser() instanceof User)) {
            return;
        }

        $request = $event->getRequest();
        if ((string) $request->cookies->get('XSRF-TOKEN', '') !== '') {
            return;
        }

        $csrfToken = bin2hex(random_bytes(32));
        $event->getResponse()->headers->setCookie(
            Cookie::create(
                'XSRF-TOKEN',
                $csrfToken,
                0,
                '/',
                null,
                false,
                false,
                false,
                Cookie::SAMESITE_LAX
            )
        );
    }

    private function requiresCsrfValidation(string $method, string $path): bool
    {
        if (!in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        foreach ($this->excludedPaths() as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return str_starts_with($path, '/api/v1/');
    }

    /** @return string[] */
    private function excludedPaths(): array
    {
        return [
            '/api/v1/auth/login',
            '/api/v1/auth/register',
            '/api/v1/auth/captcha',
            '/api/doc',
        ];
    }
}
