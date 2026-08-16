<?php

namespace App\Tests\Security\Api;

use App\Entity\User;
use App\Security\Api\TwoFactorAwareJwtSuccessHandler;
use App\Service\JWTService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class TwoFactorAwareJwtSuccessHandlerTest extends TestCase
{
    private function createToken(mixed $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    /** setId() n'existe pas publiquement : réflexion, comme SecurityAuthenticatorTest le fait déjà pour cette même entité. */
    private function createUserWithId(int $id, bool $totpEnabled): User
    {
        $user = new User();
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);

        if ($totpEnabled) {
            $user->setTotpSecret('JBSWY3DPEHPK3PXP');
            $user->setIsTwoFactorEnabled(true);
        }

        return $user;
    }

    public function testDelegatesToInnerHandlerWhenTwoFactorIsNotEnabled(): void
    {
        $expectedResponse = new Response('real-jwt-response');
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects($this->once())->method('onAuthenticationSuccess')->willReturn($expectedResponse);

        $handler = new TwoFactorAwareJwtSuccessHandler($inner, new JWTService('test-secret'));
        $response = $handler->onAuthenticationSuccess(Request::create('/api/login_check'), $this->createToken($this->createUserWithId(1, false)));

        $this->assertSame($expectedResponse, $response);
    }

    public function testDelegatesToInnerHandlerForNonAppUser(): void
    {
        // Garde-fou défensif : ne devrait jamais arriver sur ce firewall, mais
        // ne doit pas planter si un autre UserInterface se présente.
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects($this->once())->method('onAuthenticationSuccess')->willReturn(new Response());

        $handler = new TwoFactorAwareJwtSuccessHandler($inner, new JWTService('test-secret'));
        $handler->onAuthenticationSuccess(Request::create('/api/login_check'), $this->createToken(new InMemoryUser('x', 'y')));
    }

    public function testReturnsChallengeTokenInsteadOfRealJwtWhenTwoFactorIsEnabled(): void
    {
        $inner = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $inner->expects($this->never())->method('onAuthenticationSuccess');

        $jwt = new JWTService('test-secret');
        $handler = new TwoFactorAwareJwtSuccessHandler($inner, $jwt);

        $response = $handler->onAuthenticationSuccess(Request::create('/api/login_check'), $this->createToken($this->createUserWithId(42, true)));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['twoFactorRequired']);
        $this->assertIsString($data['challengeToken']);
        $this->assertArrayNotHasKey('token', $data, 'Aucun vrai jeton d\'accès ne doit fuiter avant vérification du second facteur.');

        $payload = $jwt->validate($data['challengeToken'], 'api_2fa_challenge');
        $this->assertSame(42, $payload['user_id']);
    }
}
