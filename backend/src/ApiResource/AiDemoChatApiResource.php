<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\AiDemoChatProcessor;
use App\Validator\ConversationHistory;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Démo « Votre assistant WhatsApp, en action » de l'accueil, en VRAIE IA :
 * le visiteur écrit à une boutique d'exemple (Boutique Ndinga) et voit
 * comment un assistant répondrait à SES clients. Action RPC sans entité,
 * comme AiAssistantChatApiResource (cf. son docblock pour getId()).
 *
 * Bornes plus serrées que l'assistant du site : messages courts, historique
 * de 6 tours au plus — la démo montre quelques échanges, pas une session
 * longue. Le site retombe sur les réponses scénarisées dès que `degraded`
 * est vrai ou que l'appel échoue : la démo ne paraît jamais cassée.
 */
#[ApiResource(
    shortName: 'AiDemoChat',
    description: "Démo de l'assistant WhatsApp : une boutique d'exemple répond au visiteur (Claude Haiku).",
    operations: [
        new Post(
            uriTemplate: '/assistant/demo',
            uriVariables: [],
            status: 200,
            denormalizationContext: ['groups' => ['demo_input']],
            normalizationContext: ['groups' => ['demo_output']],
            processor: AiDemoChatProcessor::class,
        ),
    ],
)]
class AiDemoChatApiResource
{
    public const MAX_MESSAGE = 300;
    public const MAX_TURN = 400;
    public const MAX_HISTORY = 6;
    public const EVENTS = ['order', 'handover'];

    public function getId(): string
    {
        return 'demo';
    }

    #[Assert\NotBlank(message: 'Le message est obligatoire.', normalizer: 'trim')]
    #[Assert\Length(max: self::MAX_MESSAGE)]
    #[Groups(['demo_input'])]
    private string $message = '';

    /** @var array<int, array{role: string, text: string}> */
    #[Assert\Count(max: self::MAX_HISTORY)]
    #[ConversationHistory]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'role' => [new Assert\NotBlank(), new Assert\Choice(choices: ['user', 'assistant'])],
                'text' => [new Assert\NotBlank(), new Assert\Length(max: self::MAX_TURN)],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ),
    ])]
    #[Groups(['demo_input'])]
    private array $history = [];

    #[Assert\Choice(choices: ['fr', 'en'], message: 'Langue non prise en charge.')]
    #[Groups(['demo_input'])]
    private string $locale = 'fr';

    #[Groups(['demo_output'])]
    private ?string $answer = null;

    /** « order » (commande notée) ou « handover » (transmis au gérant) : anime le tableau de bord. */
    #[Groups(['demo_output'])]
    private ?string $event = null;

    /** Vrai : pas de réponse IA exploitable, le site affiche la réponse scénarisée. */
    #[Groups(['demo_output'])]
    private bool $degraded = false;

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /** @return array<int, array{role: string, text: string}> */
    public function getHistory(): array
    {
        return $this->history;
    }

    /** @param array<int, array{role: string, text: string}> $history */
    public function setHistory(array $history): static
    {
        $this->history = $history;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): static
    {
        $this->answer = $answer;

        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(?string $event): static
    {
        $this->event = \in_array($event, self::EVENTS, true) ? $event : null;

        return $this;
    }

    public function isDegraded(): bool
    {
        return $this->degraded;
    }

    public function setDegraded(bool $degraded): static
    {
        $this->degraded = $degraded;

        return $this;
    }
}
