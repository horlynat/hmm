<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\SupportTicket;
use App\State\SupportTicketCreateProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Consultation/réponse à un ticket existant se font hors API Platform (accès
 * invité par jeton, pas par id) : voir App\Controller\Api\SupportTicketPublicController.
 */
#[ApiResource(
    stateOptions: new Options(entityClass: SupportTicket::class),
    shortName: 'SupportTicket',
    description: "Ressource API pour la création de tickets de support client.",
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne la liste des tickets de support (admin uniquement)."
        ),

        new Get(
            // Restreint à un id numérique : sans ce garde-fou, cette route
            // (/api/support_tickets/{id}) matche aussi le jeton hexadécimal à
            // 64 caractères de api_support_ticket_guest_view — enregistrée
            // après elle, donc jamais atteinte pour un GET tant que {id} ne
            // rejette pas ce format. Constaté en local : la route "plain"
            // était masquée, renvoyant 401 (JWT requis) au lieu du fil.
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => ['api_admin']],
            security: "is_granted('ROLE_ADMIN')",
            description: "Retourne les détails d'un ticket de support (admin uniquement)."
        ),

        new Post(
            denormalizationContext: ['groups' => ['api_public']],
            // Pas de corps de réponse : le frontend n'a besoin que du statut
            // 2xx (le jeton d'accès n'est jamais renvoyé au navigateur, cf.
            // App\Entity\SupportTicket, seulement emailé). Évite aussi le
            // piège de ClientRegistrationApiResource (le processor retourne
            // un vrai App\Entity\SupportTicket, pas la sous-classe
            // ApiResource porteuse de $message) sans avoir à choisir un
            // groupe de normalisation qui risquerait d'exposer le champ
            // $user (api_admin) — donc les données du compte lié — à un
            // visiteur non-admin.
            output: false,
            processor: SupportTicketCreateProcessor::class,
            description: "Permet à un visiteur de créer un ticket de support."
        ),

        new Delete(
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: "Supprime un ticket de support (admin uniquement)."
        ),
    ],
    normalizationContext: ['groups' => ['api_public']],
    denormalizationContext: ['groups' => ['api_public']]
)]
class SupportTicketApiResource extends SupportTicket
{
    /** Contenu du premier message — n'existe pas sur SupportTicket lui-même (il vit dans SupportTicketMessage). */
    #[Groups(['api_public'])]
    #[Assert\NotBlank(message: "Le message est obligatoire.")]
    #[Assert\Length(min: 10, max: 5000)]
    private string $message = '';

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }
}
