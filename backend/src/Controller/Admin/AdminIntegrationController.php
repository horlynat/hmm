<?php

namespace App\Controller\Admin;

use App\Entity\Integration;
use App\Enum\IntegrationTypeEnum;
use App\Form\IntegrationType;
use App\Repository\IntegrationRepository;
use App\Security\Voter\SettingsVoter;
use App\Service\AuditLogger;
use App\Service\SecretEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Intégrations externes (Slack, GitHub, CRM, API générique).
 *
 * Le secret saisi (apiKey) n'est jamais persisté en clair : il est chiffré via
 * SecretEncryptor avant d'être stocké dans Integration::apiKeyEncrypted, et
 * n'est ré-écrit que si un nouveau secret non vide est soumis (le champ du
 * formulaire est toujours vide à l'affichage — cf. IntegrationType).
 *
 * 🔒 Sécurité : réservé à SettingsVoter (ROLE_ADMIN et plus).
 */
#[Route('/admin/integration', name: 'admin_integration_')]
class AdminIntegrationController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(IntegrationRepository $integrationRepository): Response
    {
        $this->denyAccessUnlessGranted(SettingsVoter::VIEW_INTEGRATIONS);

        return $this->render('admin/integration/index.html.twig', [
            'integrations' => $integrationRepository->findAllOrderedByName(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SecretEncryptor $secretEncryptor,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::MANAGE_INTEGRATIONS);

        $integration = new Integration(IntegrationTypeEnum::API);
        $form = $this->createForm(IntegrationType::class, $integration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyApiKey($form, $integration, $secretEncryptor);

            $entityManager->persist($integration);
            $entityManager->flush();

            $auditLogger->log(Integration::class, (int) $integration->getId(), $integration->getName(), 'created');
            $entityManager->flush();

            $this->addFlash('success', 'L\'intégration a été créée avec succès.');

            return $this->redirectToRoute('admin_integration_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/integration/create.html.twig', [
            'integration' => $integration,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function update(
        Request $request,
        Integration $integration,
        EntityManagerInterface $entityManager,
        SecretEncryptor $secretEncryptor,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::MANAGE_INTEGRATIONS);

        $form = $this->createForm(IntegrationType::class, $integration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyApiKey($form, $integration, $secretEncryptor);
            $integration->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $auditLogger->log(Integration::class, (int) $integration->getId(), $integration->getName(), 'updated');
            $entityManager->flush();

            $this->addFlash('success', 'L\'intégration a été mise à jour avec succès.');

            return $this->redirectToRoute('admin_integration_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/integration/update.html.twig', [
            'integration' => $integration,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/test', name: 'test', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function test(
        Request $request,
        Integration $integration,
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::MANAGE_INTEGRATIONS);

        if (!$this->isCsrfTokenValid('admin_integration_test_' . $integration->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_integration_index');
        }

        if (!$integration->getType()->usesWebhook() || null === $integration->getWebhookUrl()) {
            $this->addFlash('error', 'Le test de connexion n\'est disponible que pour les intégrations Slack avec une URL de webhook renseignée.');

            return $this->redirectToRoute('admin_integration_index');
        }

        try {
            $response = $httpClient->request('POST', $integration->getWebhookUrl(), [
                'json' => ['text' => sprintf('✅ Test de connexion depuis le back-office (%s).', $integration->getName())],
                'timeout' => 10,
            ]);
            $success = $response->getStatusCode() < 300;
        } catch (HttpClientExceptionInterface) {
            $success = false;
        }

        $integration->recordTestResult($success);
        $entityManager->flush();

        $this->addFlash($success ? 'success' : 'error', $success
            ? 'Le message de test a été envoyé avec succès.'
            : 'Échec de l\'envoi du message de test. Vérifiez l\'URL du webhook.');

        return $this->redirectToRoute('admin_integration_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Integration $integration,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::MANAGE_INTEGRATIONS);

        if ($this->isCsrfTokenValid('admin_integration_delete_' . $integration->getId(), $request->request->get('_token'))) {
            $auditLogger->log(Integration::class, (int) $integration->getId(), $integration->getName(), 'deleted');
            $entityManager->remove($integration);
            $entityManager->flush();

            $this->addFlash('success', 'L\'intégration a été supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. Action de suppression annulée.');
        }

        return $this->redirectToRoute('admin_integration_index', [], Response::HTTP_SEE_OTHER);
    }

    private function applyApiKey(FormInterface $form, Integration $integration, SecretEncryptor $secretEncryptor): void
    {
        $apiKey = $form->get('apiKey')->getData();
        if (\is_string($apiKey) && '' !== trim($apiKey)) {
            $integration->setApiKeyEncrypted($secretEncryptor->encrypt($apiKey));
        }
    }
}
