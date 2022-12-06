<?php

namespace Cinch\Console;

use League\Tactician\Handler\Locator\HandlerLocator;
use Psr\Container\ContainerInterface;
use RuntimeException;
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
        if (!str_ends_with($commandName, 'Command'))
            throw new RuntimeException("commands must end with 'Command', found '$commandName'");

        $handlerClass = substr($commandName, 0, -7) . 'Handler';
        return $this->container->get($handlerClass);
    }
}