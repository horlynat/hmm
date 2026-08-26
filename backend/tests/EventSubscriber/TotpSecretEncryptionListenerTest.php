<?php

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\TotpSecretEncryptionListener;
use App\Service\SecretEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * SecretEncryptor est `final` (PHPStan ne peut pas typer un mock dessus) —
 * on utilise volontairement une vraie instance : un aller-retour chiffrement
 * réel est un test plus solide qu'un mock ici de toute façon.
 */
final class TotpSecretEncryptionListenerTest extends TestCase
{
    public function testDecryptsAndResetsOriginalEntityPropertyToAvoidSpuriousUpdate(): void
    {
        $secretEncryptor = new SecretEncryptor('test-app-secret');
        $ciphertext = $secretEncryptor->encrypt('PLAINSECRET');

        $user = new User();
        $user->setTotpSecret($ciphertext);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())
            ->method('setOriginalEntityProperty')
            ->with(spl_object_id($user), 'totpSecret', 'PLAINSECRET');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $listener = new TotpSecretEncryptionListener($secretEncryptor, new NullLogger());
        $listener->postLoad(new PostLoadEventArgs($user, $entityManager));

        self::assertSame('PLAINSECRET', $user->getTotpSecret());
    }

    /**
     * Repli sans casse pour un secret antérieur au chiffrement (stocké en clair,
     * donc pas un chiffré valide) : la 2FA doit continuer de fonctionner —
     * valeur laissée telle quelle, juste journalisée pour repérage.
     */
    public function testLeavesValueUntouchedAndLogsWhenDecryptionFails(): void
    {
        $secretEncryptor = new SecretEncryptor('test-app-secret');

        $user = new User();
        $user->setTotpSecret('legacy-plaintext-secret-not-valid-ciphertext');

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->never())->method('setOriginalEntityProperty');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $listener = new TotpSecretEncryptionListener($secretEncryptor, $logger);
        $listener->postLoad(new PostLoadEventArgs($user, $entityManager));

        self::assertSame('legacy-plaintext-secret-not-valid-ciphertext', $user->getTotpSecret());
    }

    public function testSkipsUsersWithoutTotpSecret(): void
    {
        $secretEncryptor = new SecretEncryptor('test-app-secret');
        $user = new User();

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener = new TotpSecretEncryptionListener($secretEncryptor, new NullLogger());
        $listener->postLoad(new PostLoadEventArgs($user, $entityManager));

        self::assertNull($user->getTotpSecret());
    }

    public function testIgnoresNonUserEntities(): void
    {
        $secretEncryptor = new SecretEncryptor('test-app-secret');
        $notAUser = new \stdClass();

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener = new TotpSecretEncryptionListener($secretEncryptor, new NullLogger());
        // N'importe : aucune exception ne doit être levée pour un objet qui n'est pas un User.
        $listener->postLoad(new PostLoadEventArgs($notAUser, $entityManager));

        $this->addToAssertionCount(1);
    }
}
