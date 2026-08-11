<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\QuoteQualifyProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Génère 1 à 2 questions de qualification dynamiques (Claude Sonnet) à partir
 * des réponses déjà saisies dans le wizard de devis, avant l'étape finale
 * "ia-qualif" du frontend (cf. useQuoteWizard.ts::fetchIaQuestions).
 *
 * Même raisonnement que AiAssistantChatApiResource : action RPC pure, aucune
 * entité Doctrine, `getId()` fixe (génération d'IRI JSON-LD). `uriTemplate`
 * volontairement plat (`/quote/qualify`, pas `/quote_requests/qualify`) pour
 * ne pas risquer d'ambiguïté avec les routes item `{id}` de
 * QuoteRequestApiResource (ressource Doctrine distincte).
 *
 * Pas de `security:` : délégué à `{ path: ^/api, roles: PUBLIC_ACCESS }`
 * (config/packages/security.yaml), comme AiAssistantChatApiResource.
 *
 * Volontairement exclus de l'entrée : name/email/phone/canal — sans rapport
 * avec la qualification du projet, aucune raison de les transmettre à Claude.
 */
#[ApiResource(
    shortName: 'QuoteQualify',
    description: 'Génère des questions de qualification dynamiques pour une demande de devis en cours de saisie.',
    operations: [
        new Post(
            uriTemplate: '/quote/qualify',
            uriVariables: [],
            status: 200,
            denormalizationContext: ['groups' => ['qualify_input']],
            normalizationContext: ['groups' => ['qualify_output']],
            processor: QuoteQualifyProcessor::class,
        ),
    ],
)]
class QuoteQualifyApiResource
{
    public function getId(): string
    {
        return 'qualify';
    }

    #[Assert\NotBlank(message: 'Le type de projet est obligatoire.')]
    #[Assert\Length(max: 120)]
    #[Groups(['qualify_input'])]
    private string $type = '';

    #[Assert\Length(max: 500)]
    #[Groups(['qualify_input'])]
    private string $categoryDetail = '';

    #[Assert\Length(max: 120)]
    #[Groups(['qualify_input'])]
    private string $source = '';

    #[Assert\Length(max: 5000)]
    #[Groups(['qualify_input'])]
    private string $description = '';

    #[Assert\Length(max: 120)]
    #[Groups(['qualify_input'])]
    private string $budget = '';

    #[Assert\Length(max: 10)]
    #[Groups(['qualify_input'])]
    private string $currency = '';

    #[Assert\Length(max: 120)]
    #[Groups(['qualify_input'])]
    private string $delai = '';

    #[Groups(['qualify_input'])]
    private string $locale = 'fr';

    /** @var string[] */
    #[Groups(['qualify_output'])]
    private array $questions = [];

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCategoryDetail(): string
    {
        return $this->categoryDetail;
    }

    public function setCategoryDetail(string $categoryDetail): static
    {
        $this->categoryDetail = $categoryDetail;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getBudget(): string
    {
        return $this->budget;
    }

    public function setBudget(string $budget): static
    {
        $this->budget = $budget;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getDelai(): string
    {
        return $this->delai;
    }

    public function setDelai(string $delai): static
    {
        $this->delai = $delai;

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

    /** @return string[] */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    /** @param string[] $questions */
    public function setQuestions(array $questions): static
    {
        $this->questions = $questions;

        return $this;
    }
}
