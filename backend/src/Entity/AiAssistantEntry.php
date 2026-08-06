<?php

namespace App\Entity;

use App\Repository\AiAssistantEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une entrée de FAQ du widget assistant IA : un libellé de suggestion
 * (chip), des mots-clés qui déclenchent la réponse quand tapés librement,
 * et la réponse elle-même — le tout bilingue. Remplace la table de
 * correspondance figée qui vivait auparavant dans le frontend, pour que
 * l'assistant reste exact quand le profil change.
 */
#[ORM\Entity(repositoryClass: AiAssistantEntryRepository::class)]
class AiAssistantEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_public', 'api_admin'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le libellé de la suggestion est obligatoire.")]
    #[Assert\Length(max: 100)]
    #[Groups(['api_public', 'api_admin'])]
    private string $chipLabel = '';

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $chipLabelEn = null;

    /**
     * Mots-clés (sous-chaînes, insensibles à la casse) qui déclenchent cette réponse.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['api_public', 'api_admin'])]
    private array $keywords = [];

    /** @var string[]|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?array $keywordsEn = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La réponse est obligatoire.")]
    #[Groups(['api_public', 'api_admin'])]
    private string $answer = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api_public', 'api_admin'])]
    private ?string $answerEn = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['api_public', 'api_admin'])]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChipLabel(): string
    {
        return $this->chipLabel;
    }

    public function setChipLabel(string $chipLabel): static
    {
        $this->chipLabel = $chipLabel;

        return $this;
    }

    public function getChipLabelEn(): ?string
    {
        return $this->chipLabelEn;
    }

    public function setChipLabelEn(?string $chipLabelEn): static
    {
        $this->chipLabelEn = $chipLabelEn;

        return $this;
    }

    /** @return string[] */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /** @param string[] $keywords */
    public function setKeywords(array $keywords): static
    {
        $this->keywords = $keywords;

        return $this;
    }

    /** @return string[]|null */
    public function getKeywordsEn(): ?array
    {
        return $this->keywordsEn;
    }

    /** @param string[]|null $keywordsEn */
    public function setKeywordsEn(?array $keywordsEn): static
    {
        $this->keywordsEn = $keywordsEn;

        return $this;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): static
    {
        $this->answer = $answer;

        return $this;
    }

    public function getAnswerEn(): ?string
    {
        return $this->answerEn;
    }

    public function setAnswerEn(?string $answerEn): static
    {
        $this->answerEn = $answerEn;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }
}
