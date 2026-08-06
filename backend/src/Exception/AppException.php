<?php

namespace App\Exception;

use ApiPlatform\Metadata\Exception\HttpExceptionInterface as ApiPlatformHttpExceptionInterface;
use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Base des exceptions métier de l'application.
 *
 * Implémente à la fois l'interface HttpException de Symfony et celle d'API
 * Platform (signatures identiques : getStatusCode()/getHeaders()) ainsi que
 * ProblemExceptionInterface. API Platform 4.3 lit nativement ces interfaces
 * dans ApiPlatform\State\ApiResource\Error::createFromException() pour
 * produire une réponse JSON (problem+json/jsonld/jsonapi) correcte — aucun
 * exception_to_status ni normalizer custom n'est donc nécessaire. Côté HTML,
 * Symfony\Bridge\Twig\ErrorRenderer\TwigErrorRenderer se base uniquement sur
 * le code HTTP (getStatusCode()) pour choisir le template.
 */
abstract class AppException extends \Exception implements HttpExceptionInterface, ApiPlatformHttpExceptionInterface, ProblemExceptionInterface
{
    /**
     * @param array<string, mixed> $context Données internes de journalisation (IDs, e-mail, etc.) — jamais exposées au client.
     */
    public function __construct(
        string $message,
        private readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $this->getHttpStatusCode(), $previous);
    }

    abstract public function getHttpStatusCode(): int;

    abstract public function getTitle(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Si true, ExceptionSubscriber déclenche une alerte admin même si le
     * code HTTP est un 4xx (par défaut, seules les erreurs 5xx notifient).
     */
    public function shouldNotify(): bool
    {
        return false;
    }

    // --- ApiPlatform\Metadata\Exception\ProblemExceptionInterface ---

    public function getType(): string
    {
        return 'about:blank';
    }

    public function getStatus(): ?int
    {
        return $this->getHttpStatusCode();
    }

    public function getDetail(): ?string
    {
        return $this->getMessage();
    }

    public function getInstance(): ?string
    {
        return null;
    }

    // --- HttpExceptionInterface (Symfony + API Platform, signatures identiques) ---

    public function getStatusCode(): int
    {
        return $this->getHttpStatusCode();
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
