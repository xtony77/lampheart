<?php

namespace lampheart\Providers;

class BaseProvider
{
    protected $providers = [];

    protected $defaultProviders = [
        'LogProvider',
        'DatabaseProvider',
        'RouteProvider',
    ];

    public function make()
    {
        $this->loadProvidersClass();
    }

    private function loadProvidersClass()
    {
        foreach ($this->defaultProviders as $key => $fileName) {
            $path = __DIR__.'/'.$fileName.'.php';

            if (!is_file($path)) {
                throw new \Exception('Default provider not exist: '.$path);
            }

            require_once $path;
            $class = __NAMESPACE__.'\\'.$fileName;
            new $class;
        }

        foreach ($this->providers as $key => $fileName) {
            $path = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))).'/app/Providers/'.$fileName.'.php';

            if (!is_file($path)) {
                throw new \Exception('Provider not exist: '.$path);
            }

            require_once $path;
            $class = __NAMESPACE__.'\\'.$path;
            new $class;
        }
    }
}