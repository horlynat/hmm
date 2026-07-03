<?php

// src/EventListener/LoginListener.php
namespace App\EventListener;

use App\Entity\LoginHistory;
use App\Entity\User;
use App\Message\LoginNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
#[AsEventListener(event: InteractiveLoginEvent::class, method: 'onLogin')]
class LoginListener
{
    public function __construct(
        private MessageBusInterface    $bus,
        private RequestStack           $requestStack,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $ip     = $request->getClientIp() ?? '0.0.0.0';
        $device = $request->headers->get('User-Agent') ?? 'Appareil inconnu';

        $user->setLastIp($ip);
        $user->setLastDevice($device);
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->bus->dispatch(new LoginNotification(
            userId:   $user->getId(),
            email:    $user->getEmail(),
            fullName: $user->getFullName(),
            ip:       $ip,
            device:   $device,
            date:     new \DateTimeImmutable(),
        ));
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        $loginHistory = new LoginHistory();
        $loginHistory->setUser($user);
        $loginHistory->setIp($event->getRequest()->getClientIp());
        $loginHistory->setDevice($event->getRequest()->headers->get('User-Agent'));
        $loginHistory->setLoginAt(new \DateTimeImmutable());

        $this->entityManager->persist($loginHistory);
        $this->entityManager->flush();
    }
}