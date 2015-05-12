<?php
/**
 * @author Lukáš Brzák <lukas.brzak@email.cz>
 * Date: 10.5.15 16:42
 */

namespace Vegan\Bundle\MenuBundle\Menu;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RouteCollection;

/**
 * VeganUrlGenerator implements clasic Symfony UrlGenerator, but can handle RouteCollection from VeganDynamicRouter
 */
class VeganUrlGenerator
{
    /** @var ContainerInterface $container */
    protected $container = null;

    /** @var \Symfony\Component\Routing\RequestContext $context */
    protected $context = null;

    /** @var \Symfony\Bundle\FrameworkBundle\Routing\Router $router */
    protected $router = null;

    /** @var array|null|RouteCollection $routeCollection */
    protected $routeCollection = array();

    /** @var array|null|RouteCollection $dynamicRouteCollection */
    protected $dynamicRouteCollection = array();

    /** @var UrlGenerator[]|array $generators */
    protected $generators = array();

    public function __construct(ContainerInterface $container = null)
    {
        if (null !== $container) {
            $this->setContainer($container);
        }
    }


    public function generate($routeName, $parameters, $referencePath = UrlGenerator::ABSOLUTE_PATH)
    {
        $url = null;
        foreach ($this->generators as $name => $generator) {
            try {
                $url = $generator->generate($routeName, $parameters, $referencePath);
            } catch (\Exception $e) {
                continue;
            }
        }
        return $url;
    }

    public function getRoute($routeName)
    {
        $route = null;
        if ($this->dynamicRouteCollection instanceof RouteCollection) {
            $route = $this->dynamicRouteCollection->get($routeName);
        }
        if (null === $route) {
            $route = $this->routeCollection->get($routeName);
        }
        return $route;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
        $this->router = $container->get('router');
        $this->routeCollection = $this->router->getRouteCollection();
        $this->context = $this->router->getContext();

        if ($container->has('vegan_router.generator')) {
            $this->dynamicRouteCollection = $container->get('vegan_router.generator')->getRoutes();
            $this->generators['dynamic'] = new UrlGenerator($this->dynamicRouteCollection, $this->context);
        }
        $this->generators['default'] = new UrlGenerator($this->routeCollection, $this->context);

        return $this;
    }

}
