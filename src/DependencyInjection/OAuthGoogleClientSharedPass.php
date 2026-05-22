<?php

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * League OAuth provider caches redirect_uri at construction. As a shared singleton it can keep
 * the wrong host (e.g. localhost vs 127.0.0.1) across requests and break Google's token exchange.
 */
class OAuthGoogleClientSharedPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (['knpu.oauth2.provider.google', 'knpu.oauth2.client.google'] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setShared(false);
            }
        }
    }
}
