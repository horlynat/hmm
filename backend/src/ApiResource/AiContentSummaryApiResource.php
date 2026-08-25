<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\AiContentSummaryProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Résumé éditorial d'un contenu du portfolio (article de blog, projet) par
 * l'assistant IA — distinct de AiAssistantChatApiResource (FAQ sur le profil
 * de Horlynat : corpus curé en <context>, système prompt qui dit
 * explicitement de se méfier de tout contenu fourni dans la question et de
 * ne s'appuyer QUE sur ce corpus). Réutiliser ce même endpoint pour résumer
 * un article a été testé et produit systématiquement des réponses évasives
 * ("je n'ai pas accès au contenu complet de cet article") — le prompt lui
 * demandait littéralement de ne pas faire confiance au texte fourni. Ici,
 * `content` EST la matière à résumer, pas une donnée à vérifier contre un
 * corpus tiers : système prompt dédié, cf. AiContentSummaryProcessor.
 *
 * Mêmes conventions que AiAssistantChatApiResource : ressource RPC pure (pas
 * d'entité Doctrine liée), getId() renvoie une valeur fixe (blank node
 * JSON-LD, jamais utilisée pour re-fetch quoi que ce soit), sécurité
 * déléguée à `{ path: ^/api, roles: PUBLIC_ACCESS }`
 * (config/packages/security.yaml).
 */
#[ApiResource(
    shortName: 'AiContentSummary',
    description: "Résume un article ou un projet du portfolio de façon captivante, à partir de son texte intégral fourni par l'appelant — endpoint distinct du chat FAQ, dont le prompt système est incompatible avec cet usage.",
    operations: [
        new Post(
            uriTemplate: '/assistant/summarize',
            uriVariables: [],
            status: 200,
            denormalizationContext: ['groups' => ['summarize_input']],
            normalizationContext: ['groups' => ['summarize_output']],
            processor: AiContentSummaryProcessor::class,
        ),
    ],
)]
class AiContentSummaryApiResource
{
    public function getId(): string
    {
        return 'summarize';
    }

    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 300, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['summarize_input'])]
    private string $title = '';

    /**
     * Texte intégral (brut, sans HTML) de l'article/projet à résumer — la
     * matière du résumé, jamais une instruction (cf. system prompt du
     * processor). Contenu de confiance (rédigé côté admin), jamais passé au
     * filtre anti-injection contrairement à `question` ci-dessous.
     */
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    #[Assert\Length(max: 6000, maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['summarize_input'])]
    private string $content = '';

    #[Assert\Choice(choices: ['article', 'project'], message: 'Type de contenu invalide.')]
    #[Groups(['summarize_input'])]
    private string $contentType = 'article';

    /** Question de suivi du visiteur — vide sur le premier appel (résumé initial, cf. processor). */
    #[Assert\Length(max: 1000, maxMessage: 'La question ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['summarize_input'])]
    private string $question = '';

    /** @var array<int, array{role: string, text: string}> */
    #[Assert\Count(max: 8, maxMessage: "L'historique ne peut pas dépasser {{ limit }} échanges.")]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'role' => [new Assert\NotBlank(), new Assert\Choice(choices: ['user', 'assistant'])],
                'text' => [new Assert\NotBlank(), new Assert\Length(max: 1500)],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ),
    ])]
    #[Groups(['summarize_input'])]
    private array $history = [];

    #[Groups(['summarize_input'])]
    private string $locale = 'fr';

    #[Groups(['summarize_output'])]
    private ?string $answer = null;

    /** @var string[] */
    #[Groups(['summarize_output'])]
    private array $suggestions = [];

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): static
    {
        $this->contentType = $contentType;

        return $this;
    }

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
