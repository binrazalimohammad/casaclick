<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();
        
        // Redirect based on role
        if (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_STAFF', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        } else {
            return new RedirectResponse($this->urlGenerator->generate('app_product_index'));
        }
    }
}

