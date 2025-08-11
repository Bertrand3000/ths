<?php

namespace App\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tehou');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('api')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('token')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('debug')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('token')->defaultValue('')->end()
                    ->end()
                ->end()
                ->arrayNode('syslog')
                    ->children()
                        ->integerNode('batch_size')->defaultValue(1000)->end()
                        ->integerNode('max_processing_time')->defaultValue(300)->end()
                        ->integerNode('max_errors')->defaultValue(100)->end()
                        ->integerNode('lock_timeout')->defaultValue(300)->end()
                        ->arrayNode('regex_patterns')
                            ->children()
                                ->arrayNode('connection')
                                    ->prototype('scalar')->end()
                                ->end()
                                ->arrayNode('disconnection')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
