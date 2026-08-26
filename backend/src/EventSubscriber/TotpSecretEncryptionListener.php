<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\SecretEncryptor;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * Déchiffre User::totpSecret juste après le chargement depuis la base —
 * l'écriture (chiffrement) se fait explicitement côté appelant
 * (TwoFactorController::setup(), ApiTwoFactorSetupController::confirm()),
 * même convention que SecretEncryptor pour Integration::apiKeyEncrypted
 * (cf. AdminIntegrationController). Toute la valeur en mémoire redevient donc
 * le secret en clair pour le reste de la requête — c'est ce que
 * User::getTotpAuthenticationConfiguration() doit renvoyer à Scheb 2FA Bundle
 * pour vérifier un code TOTP.
 *
 * ⚠️ setOriginalEntityProperty() après déchiffrement : sans ça, le "before"
 * connu de Doctrine (chiffré, lu en base) diverge du "after" en mémoire
 * (déchiffré) dès ce postLoad, et TOUT flush ultérieur sur ce User — même pour
 * un champ sans rapport — réécrirait le secret EN CLAIR en base (Doctrine
 * détecterait un faux changement et le persisterait). Mécanisme interne mais
 * public de Doctrine (UnitOfWork::setOriginalEntityProperty(), même
 * technique que ambta/doctrine-encrypt-bundle pour ce problème précis).
 *
 * Repli sans casse pour les secrets antérieurs à ce chiffrement (stockés en
 * clair) : le déchiffrement échoue proprement (SecretEncryptor::decrypt()
 * lève une exception sur une entrée qui n'est pas un chiffré valide), la
 * valeur d'origine est laissée telle quelle et la 2FA continue de fonctionner
 * — seulement journalisé, pour repérer les comptes à faire régénérer.
 */
final class TotpSecretEncryptionListener implements EventSubscriber
{
    public function __construct(
        private readonly SecretEncryptor $secretEncryptor,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return [Events::postLoad];
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $user = $args->getObject();
        if (!$user instanceof User) {
            return;
        }

        $stored = $user->getTotpSecret();
        if (null === $stored) {
            return;
        }

        try {
            $plaintext = $this->secretEncryptor->decrypt($stored);
        } catch (\RuntimeException) {
            $this->logger->warning('TOTP secret non chiffré détecté (probablement antérieur au chiffrement du champ) — 2FA reste fonctionnelle, migration recommandée.', [
                'user_id' => $user->getId(),
            ]);

            return;
        }

        $user->setTotpSecret($plaintext);
        $args->getObjectManager()->getUnitOfWork()->setOriginalEntityProperty(spl_object_id($user), 'totpSecret', $plaintext);
    }
}
