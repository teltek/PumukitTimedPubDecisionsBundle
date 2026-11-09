<?php

declare(strict_types=1);

namespace Pumukit\TimedPubDecisionsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_timed_pub_decisions');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->booleanNode('enable')
            ->defaultTrue()
            ->info('If false, the decisions are not temporized, the pages return 404 and nothing else of the bundle is loaded: no commands, listener, menu or permission')
            ->end()
            ->scalarNode('default_group_temporized')
            ->defaultValue('year')
            ->info('Group multimedia objects by...')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
