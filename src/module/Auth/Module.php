<?php

namespace Auth;
/*   NICE */
/*   NICE */
v
use Zend\EventManager\EventInterface;
use ZfcRbac\View\Strategy\RedirectStrategy;
use ZfcRbac\View\Strategy\UnauthorizedStrategy;

class Module
{
    public function onBootstrap(EventInterface $e)
    {
        $target = $e->getTarget();

        /** @var ServiceManager $serviceLocator */
        $serviceLocator = $target->getServiceManager();

        /** @var EventManager $eventManager */
        $eventManager = $target->getEventManager();

        $eventManager->attach($serviceLocator->get(UnauthorizedStrategy::class));
        $eventManager->attach($serviceLocator->get(RedirectStrategy::class));
    }
/*   NICE */

    public function getConfig()
    {

        return include __DIR__ . '/config/module.config.php';
    }

   /*   NICE */
/*   NICE */
/*   NICE */
/*   NICE */
 public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}

/*        /** @var ServiceManager $serviceLocator */
