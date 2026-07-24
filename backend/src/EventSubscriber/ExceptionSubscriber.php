<?php

namespace App\EventSubscriber;

use App\Exception\AppException;
use App\Exception\JWTException;
use App\Service\ErrorNotifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Premier subscriber au niveau HttpKernel de l'application (les autres
 * EventSubscriber existants — AlertNotificationSubscriber, UserRoleSubscriber
 * — sont des event listeners Doctrine ORM, pas kernel.exception).
 *
 * Rôle strictement limité à la journalisation (bon canal Monolog) et à la
 * notification admin (ErrorNotifier) pour les erreurs graves. Ne construit
 * JAMAIS la réponse HTTP : côté API (requêtes _api_respond), API Platform a
 * déjà son propre ExceptionListener qui rend le JSON à partir des interfaces
 * implémentées par AppException (voir AppException::class) ; côté HTML,
 * Symfony\Bridge\Twig\ErrorRenderer\TwigErrorRenderer choisit et rend déjà
 * automatiquement templates/bundles/TwigBundle/Exception/error{code}.html.twig.
 *
 * Toute la logique est protégée par un try/catch global : un échec de
 * journalisation ou de notification ne doit jamais empêcher la réponse
 * d'erreur normale de partir (même philosophie que AdminAlertNotifier).
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.app_errors')] private readonly LoggerInterface $appErrorsLogger,
        #[Autowire(service: 'monolog.logger.security_errors')] private readonly LoggerInterface $securityErrorsLogger,
        #[Autowire(service: 'monolog.logger.business_errors')] private readonly LoggerInterface $businessErrorsLogger,
        private readonly ErrorNotifier $errorNotifier,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        try {
            $this->handle($event);
        } catch (\Throwable $e) {
            // La gestion d'erreur elle-même ne doit jamais faire tomber la requête.
            $this->appErrorsLogger->critical('ExceptionSubscriber a échoué.', ['error' => $e->getMessage()]);
        }
    }

    private function handle(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $statusCode = $this->resolveStatusCode($throwable);

        [$logger, $level] = $this->resolveLoggerAndLevel($throwable);

        $context = [
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
            'status' => $statusCode,
        ];
        if ($throwable instanceof AppException) {
            $context['business_context'] = $throwable->getContext();
        }

        $logger->log($level, $throwable->getMessage(), $context);

        $shouldNotify = $statusCode >= 500 || ($throwable instanceof AppException && $throwable->shouldNotify());
        if ($shouldNotify) {
            $this->errorNotifier->notify($throwable, $statusCode, $context);
        }
    }

    private function resolveStatusCode(\Throwable $throwable): int
    {
        // AppException implémente déjà HttpExceptionInterface (voir AppException::class).
        return $throwable instanceof HttpExceptionInterface ? $throwable->getStatusCode() : 500;
    }

    /**
     * @return array{0: LoggerInterface, 1: string}
     */
    private function resolveLoggerAndLevel(\Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof AppException => [$this->businessErrorsLogger, 'warning'],
            $throwable instanceof AuthenticationException,
            $throwable instanceof AccessDeniedException,
            $throwable instanceof AccessDeniedHttpException,
            $throwable instanceof JWTException => [$this->securityErrorsLogger, 'warning'],
            default => [$this->appErrorsLogger, 'error'],
        };
    }
}
