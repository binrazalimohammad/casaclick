<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailVerificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sendVerificationEmail(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->setVerificationToken($token);

        $verifyUrl = $this->urlGenerator->generate('app_verify_email', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@casaclick.example', 'CasaClick'))
            ->to($user->getEmail())
            ->subject('Verify your CasaClick account')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verifyUrl' => $verifyUrl,
                'expiresAt' => new \DateTimeImmutable('+24 hours'),
            ]);

        $this->mailer->send($email);
    }
}
