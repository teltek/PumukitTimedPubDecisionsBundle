<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PumukitTimedPubDecisionsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('pumukit_timed_pub_decisions.default_group_temporized', $config['default_group_temporized']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('pumukit_timed_pub_decisions.yaml');

        $permissions = [['role' => 'ROLE_ACCESS_TIMEDPUBDECISIONS_TIMELINE', 'description' => 'Access timedpubdecisions timeline']];
        $newPermissions = array_merge($container->getParameter('pumukitschema.external_permissions'), $permissions);
        $container->setParameter('pumukitschema.external_permissions', $newPermissions);
    }
}
