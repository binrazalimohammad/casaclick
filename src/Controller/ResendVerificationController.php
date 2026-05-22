<?php

namespace App\Controller;

use App\Exception\MailNotConfiguredException;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ResendVerificationController extends AbstractController
{
    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        UserRepository $userRepository,
        EmailVerificationService $verificationService,
        EntityManagerInterface $em,
        LoggerInterface $logger,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('resend_verification', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Security token expired. Please try again.');
                return $this->redirectToRoute('app_resend_verification');
            }

            $email = trim((string) $request->request->get('email', ''));
            if ($email !== '') {
                $user = $userRepository->findOneBy(['email' => $email]);
                if ($user !== null && !$user->isEmailVerified()) {
                    try {
                        $verificationService->sendVerificationEmail($user);
                        $em->flush();
                    } catch (MailNotConfiguredException $e) {
                        $this->addFlash('error', $e->getMessage());

                        return $this->redirectToRoute('app_resend_verification');
                    } catch (\Throwable $e) {
                        $logger->error('Resend verification email failed.', ['exception' => $e]);
                        $this->addFlash('error', 'We could not send an email right now. Check your mail settings (MAILER_DSN) or try again later.');
                        return $this->redirectToRoute('app_resend_verification');
                    }
                }
            }

            $this->addFlash(
                'success',
                'If an account exists for that email and it is not verified yet, we have sent a new verification link. Check your inbox and spam folder.'
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/resend_verification.html.twig');
    }
}
