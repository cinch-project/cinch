<?php

namespace Cinch\Console;

use League\Tactician\Handler\Locator\HandlerLocator;
use Psr\Container\ContainerInterface;
use Throwable;

class ContainerHandlerLocator implements HandlerLocator
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @throws Throwable
     */
    public function getHandlerForCommand($commandName)
    {
        return $this->container->get($commandName . 'Handler');
    }
}