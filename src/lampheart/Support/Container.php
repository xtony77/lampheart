<?php

namespace lampheart\Support;

use DI\Container as DiContainer;

trait Container
{
    /**
     * Returns an entry of the container by its class path.
     *
     * @param string $class
     * @return mixed
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function container(string $class)
    {
        return $this->diContainer()->get($class);
    }

    private function diContainer()
    {
        return new DiContainer;
    }
}