<?php

namespace App\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TehouExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set debug parameters
        $container->setParameter('tehou.debug.enabled', $config['debug']['enabled']);
        $container->setParameter('tehou.debug.token', $config['debug']['token']);

        // Set syslog parameters
        $container->setParameter('tehou.syslog.batch_size', $config['syslog']['batch_size']);
        $container->setParameter('tehou.syslog.max_processing_time', $config['syslog']['max_processing_time']);
        $container->setParameter('tehou.syslog.max_errors', $config['syslog']['max_errors']);
        $container->setParameter('tehou.syslog.lock_timeout', $config['syslog']['lock_timeout']);
        $container->setParameter('tehou.syslog.regex_patterns.connection', $config['syslog']['regex_patterns']['connection']);
        $container->setParameter('tehou.syslog.regex_patterns.disconnection', $config['syslog']['regex_patterns']['disconnection']);
    }

    public function getAlias(): string
    {
        return 'tehou';
    }
}
