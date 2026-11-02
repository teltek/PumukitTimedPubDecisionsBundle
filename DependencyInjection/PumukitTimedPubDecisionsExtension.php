<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\DependencyInjection;

use Pumukit\CoreBundle\DependencyInjection\FrontendAssets;
use Pumukit\TimedPubDecisionsBundle\EventListener\BackofficeListener;
use Pumukit\TimedPubDecisionsBundle\Services\MenuService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

class PumukitTimedPubDecisionsExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        FrontendAssets::register($container, dirname(__DIR__), '@pumukit/timed-pub-decisions');
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('pumukit_timed_pub_decisions.enable', $config['enable']);
        $container->setParameter('pumukit_timed_pub_decisions.default_group_temporized', $config['default_group_temporized']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('pumukit_timed_pub_decisions.yaml');

        if (!$config['enable']) {
            $container->removeDefinition(MenuService::class);
            $container->removeDefinition(BackofficeListener::class);

            return;
        }

        $container->setParameter('pumukitschema.external_permissions', array_merge($container->getParameter('pumukitschema.external_permissions'), [
            ['role' => 'ROLE_ACCESS_TIMEDPUBDECISIONS_TIMELINE', 'description' => 'Access timedpubdecisions timeline'],
        ]));
    }
}
