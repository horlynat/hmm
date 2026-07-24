# Architecture de gestion des erreurs

## Pipeline

```
Requête HTTP
   │
   ▼
Exception levée (métier ou technique)
   │
   ▼
kernel.exception
   │
   ├─► ExceptionSubscriber (priorité 100)
   │      │  journalise dans le bon canal Monolog (app_errors / security_errors / business_errors)
   │      └─ si grave (5xx, ou AppException::shouldNotify() === true) → ErrorNotifier
   │                                                                       │
   │                                                                       └─ email admin (anti-spam 1/h/classe)
   │
   └─► rendu de la réponse, natif, sans code applicatif :
          - requête API (_api_respond) → ApiPlatform\Symfony\EventListener\ExceptionListener,
            qui lit les interfaces implémentées par AppException (ProblemExceptionInterface,
            HttpExceptionInterface) pour produire le JSON (problem+json / jsonld / jsonapi)
          - requête HTML → Symfony\Bridge\Twig\ErrorRenderer\TwigErrorRenderer, qui choisit
            templates/bundles/TwigBundle/Exception/error{code}.html.twig (fallback: error.html.twig)
```

`ExceptionSubscriber` ne construit **jamais** la réponse HTTP lui-même (pas de `$event->setResponse()`) : c'est déjà le rôle d'API Platform et de Symfony, qui le font correctement sans configuration supplémentaire (voir plus bas pourquoi).

## Ajouter une nouvelle `AppException`

1. Étendre `App\Exception\AppException` (`src/Exception/AppException.php`).
2. Implémenter `getHttpStatusCode(): int` et `getTitle(): ?string`.
3. Optionnel :
   - `shouldNotify(): bool` → `true` si ce cas métier doit déclencher une alerte admin même en 4xx (par défaut `false` : les 4xx sont attendus, seuls les 5xx notifient automatiquement).
   - passer un `context` au constructeur (`['userId' => ..., 'email' => ...]`) pour enrichir les logs et l'email d'alerte — jamais exposé dans la réponse HTTP au client.
4. Rien à toucher dans la config (`api_platform.yaml`, `services.yaml`, monolog, etc.) : tout est déjà branché.

Exemple minimal :

```php
final class QuoteRequestException extends AppException
{
    public function getHttpStatusCode(): int { return 422; }
    public function getTitle(): ?string { return 'Demande de devis invalide'; }
}
```

### Exemple réel déjà en place : `ConflictException`

`src/State/CollaboratorRegistrationProcessor.php` capture `Doctrine\DBAL\Exception\UniqueConstraintViolationException` autour du `flush()` et relance une `App\Exception\ConflictException` (409). Ce cas ne peut survenir qu'en cas de course entre deux requêtes concurrentes sur le même email (le validateur `UniqueEntity` a déjà été passé avant d'arriver au processor, mais ne protège pas contre une deuxième requête arrivée entre-temps). Sans cette exception, l'API renvoyait un 500 SQL brut au client public.

## Pourquoi ni `exception_to_status` ni normalizer custom

API Platform 4.3 (`ApiPlatform\State\ApiResource\Error::createFromException()`) lit nativement, dans l'ordre :

1. le mapping `exception_to_status` (global ou par opération) s'il existe ;
2. `Symfony\Component\HttpKernel\Exception\HttpExceptionInterface` ;
3. `ApiPlatform\Metadata\Exception\ProblemExceptionInterface` (title/detail/status/type/instance) ;
4. `ApiPlatform\Metadata\Exception\HttpExceptionInterface` (signature identique à celle de Symfony).

`AppException` implémente les trois interfaces (2, 3, 4) directement — API Platform produit donc déjà la bonne réponse JSON sans qu'aucune config `exception_to_status` ni normalizer dédié soit nécessaire. Ajouter l'un ou l'autre serait redondant.

Côté HTML, `TwigErrorRenderer::findTemplate()` ne regarde que le code HTTP (`getStatusCode()`) pour choisir le template — pas besoin de `Controller/ErrorController.php` ni de la clé `error_controller` dans `framework.yaml`.

## Seuils de notification admin

- **5xx (erreurs imprévues)** : toujours notifiés.
- **AppException (4xx métier)** : notifiés uniquement si `shouldNotify()` retourne `true` (par défaut `false` — un 409/422 attendu ne justifie pas une alerte).
- **Anti-spam** : au plus **1 email par heure et par classe d'exception** (`::class`, pas par message), via le rate limiter `limiter.error_notification` (`config/packages/framework.yaml`) sur un pool de cache dédié `cache.error_notifier` (`config/packages/cache.yaml`). Un incident qui génère 500 requêtes en boucle ne déclenche donc qu'un seul email tant qu'il dure.
- `ErrorNotifier` utilise `EmailManager::sendNow()` (SMTP synchrone), pas `sendAsync()` : la voie asynchrone passe par Messenger, potentiellement backé par la même base de données que celle en panne — `sendNow()` est un chemin de défaillance indépendant.
- Comme `AdminAlertNotifier`, `ErrorNotifier` n'échoue jamais l'appelant : toute erreur pendant la notification est journalisée (canal `app_errors`) et avalée.

## Canaux Monolog

| Canal              | Contenu                                              | Handler                                          |
|---------------------|-------------------------------------------------------|---------------------------------------------------|
| `app_errors`         | Throwable non prévu / 5xx                             | Pipeline `main` existant (`fingers_crossed`)       |
| `security_errors`     | 401/403, `AuthenticationException`, `JWTException`... | Handler direct, non bufferisé                      |
| `business_errors`     | `AppException` (4xx métier)                           | Handler direct, non bufferisé                      |

`fingers_crossed` (utilisé par le pipeline `main`) ne conserve son buffer que si un enregistrement de niveau ERROR ou plus survient **dans la même requête**. Comme `security_errors`/`business_errors` sont journalisés en `warning`, les laisser sur `main` les ferait disparaître silencieusement dès qu'aucune autre erreur ne survient dans la requête — d'où leurs handlers directs dédiés.

## Limite connue

Le contenu de `AppException::getContext()` n'apparaît pas automatiquement dans le corps JSON de la réponse API (seuls `title/detail/status/type/instance` sont lus par `Error::createFromException()`) — il sert uniquement aux logs et à l'email d'alerte. Si des erreurs de validation champ par champ doivent un jour apparaître dans la réponse, l'extension point correct est `ApiPlatform\Validator\Exception\ConstraintViolationListAwareExceptionInterface` (pour de vraies violations du Validator Symfony), pas `AppException`.
