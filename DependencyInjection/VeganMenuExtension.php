<?php

namespace Vegan\MenuBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class VeganMenuExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $manager = preg_replace('/\@/', '', $config['entity_manager']);
        $managers = $container->getParameter('doctrine.entity_managers');

        if (array_key_exists($manager, $managers)) {
            $entityManager = $managers[$manager];
        } else if (in_array($manager, array_values($managers))) {
            $entityManager = $manager;
        } else {
            throw new \InvalidArgumentException("Configuration of vegan_menu `entity_manager` is invalid! EntityManager '{$manager}' was not found in container!");
        }

        $container->setAlias('vegan.menu.entity_manager', $entityManager);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
