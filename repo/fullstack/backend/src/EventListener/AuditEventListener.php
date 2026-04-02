<?php

namespace App\EventListener;

use App\Entity\Appointment;
use App\Entity\CredentialSubmission;
use App\Entity\Practitioner;
use App\Entity\Question;
use App\Entity\User;
use App\Service\AuditLogService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDoctrineListener(event: Events::onFlush)]
class AuditEventListener
{
    private const AUDITABLE = [
        User::class,
        Practitioner::class,
        CredentialSubmission::class,
        Appointment::class,
        Question::class,
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly Security $security,
        private readonly RequestStack $requestStack
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }
        $uow = $em->getUnitOfWork();
        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$this->isAuditable($entity)) {
                continue;
            }
            $this->auditLogService->log($userId, 'CREATE', $entity::class, method_exists($entity, 'getId') ? $entity->getId() : null, null, $this->entitySnapshot($entity), $ip);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->isAuditable($entity)) {
                continue;
            }
            $changes = $uow->getEntityChangeSet($entity);
            $old = [];
            $new = [];
            foreach ($changes as $field => $change) {
                $old[$field] = $this->normalize($change[0] ?? null);
                $new[$field] = $this->normalize($change[1] ?? null);
            }
            $this->auditLogService->log($userId, 'UPDATE', $entity::class, method_exists($entity, 'getId') ? $entity->getId() : null, $old, $new, $ip);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if (!$this->isAuditable($entity)) {
                continue;
            }
            $this->auditLogService->log($userId, 'DELETE', $entity::class, method_exists($entity, 'getId') ? $entity->getId() : null, $this->entitySnapshot($entity), null, $ip);
        }
    }

    private function isAuditable(object $entity): bool
    {
        return in_array($entity::class, self::AUDITABLE, true);
    }

    private function entitySnapshot(object $entity): array
    {
        $data = [];
        foreach (get_object_vars($entity) as $key => $value) {
            $data[$key] = $this->normalize($value);
        }

        return $data;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if (is_object($value) && method_exists($value, 'getId')) {
            return $value->getId();
        }
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        return $value;
    }
}
