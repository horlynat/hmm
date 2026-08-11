<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\AiAssistantChatProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Point d'entrée public du chat conversationnel (texte libre → Gemini+Claude).
 *
 * Contrairement aux autres ressources d'action du projet (ContactMessage,
 * QuoteRequest...), celle-ci n'étend AUCUNE entité Doctrine : c'est une
 * action RPC pure (aucun identifiant, aucune ressource individuelle
 * récupérable ensuite), pas une création au sens REST/CRUD. La lier à une
 * entité (même une transitoire, jamais persistée) fait échouer la
 * génération d'IRI JSON-LD ("Unable to generate an IRI...", car API
 * Platform s'attend à pouvoir construire un lien vers l'item créé). Le log
 * anonymisé réellement persisté (App\Entity\AiAssistantConversationLog) est
 * une entité à part entière, totalement découplée de cette ressource — elle
 * ne porte jamais `question`/`answer` et n'est jamais exposée via l'API.
 *
 * Pas de `security:` : délégué à `{ path: ^/api, roles: PUBLIC_ACCESS }`
 * (config/packages/security.yaml), comme les GetCollection de
 * AiAssistantSettings/AiAssistantEntry.
 *
 * `getId()` : API Platform génère toujours, même sans opération Get
 * déclarée, une route "item" interne non exposée (utilisée pour la
 * génération d'IRI JSON-LD) qui s'attend à lire une propriété `id` — sans
 * elle (ou si elle vaut null), la sérialisation de la réponse échoue avec
 * "Unable to generate an IRI...". Comme cette ressource n'a pas de vraie
 * identité, on renvoie une valeur fixe (équivalent d'un blank node JSON-LD)
 * plutôt qu'un vrai identifiant — jamais utilisée pour re-fetch quoi que ce
 * soit, cette route interne n'est de toute façon jamais exposée.
 */
#[ApiResource(
    shortName: 'AiAssistantChat',
    description: "Pose une question libre à l'assistant IA (Gemini pour le RAG, Claude pour le raisonnement), avec grounding sur le profil du portfolio.",
    operations: [
        new Post(
            uriTemplate: '/assistant/chat',
            uriVariables: [],
            status: 200,
            denormalizationContext: ['groups' => ['ask_input']],
            normalizationContext: ['groups' => ['ask_output']],
            processor: AiAssistantChatProcessor::class,
        ),
    ],
)]
class AiAssistantChatApiResource
{
    public function getId(): string
    {
        return 'chat';
    }

    #[Assert\NotBlank(message: 'La question est obligatoire.')]
    #[Assert\Length(max: 1000, maxMessage: 'La question ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['ask_input'])]
    private string $question = '';

    /** @var array<int, array{role: string, text: string}> */
    #[Assert\Count(max: 8, maxMessage: "L'historique ne peut pas dépasser {{ limit }} échanges.")]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'role' => [new Assert\NotBlank(), new Assert\Choice(choices: ['user', 'assistant'])],
                'text' => [new Assert\NotBlank(), new Assert\Length(max: 1000)],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ),
    ])]
    #[Groups(['ask_input'])]
    private array $history = [];

    #[Groups(['ask_input'])]
    private string $locale = 'fr';

    #[Groups(['ask_output'])]
    private ?string $answer = null;

    /**
     * Questions de suivi contextuelles proposées par Claude à la fin de sa réponse
     * (jamais renseigné sur un repli/blocage — le front garde alors les suggestions
     * précédentes plutôt que d'afficher une liste vide). Cf. AiAssistantChatProcessor.
     *
     * @var string[]
     */
    #[Groups(['ask_output'])]
    private array $suggestions = [];

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;

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

    /** @return string[] */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /** @param string[] $suggestions */
    public function setSuggestions(array $suggestions): static
    {
        $this->suggestions = $suggestions;

        return $this;
    }
}
