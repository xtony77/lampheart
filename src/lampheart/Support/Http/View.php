<?php

namespace lampheart\Support\Http;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

trait View
{
    public function view($viewName, $templateData)
    {
        $basePath = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));

        // Configuration
        // Note that you can set several directories where your templates are located
        $pathsToTemplates = [$basePath . '/resources/views'];
        $pathToCompiledTemplates = $basePath . '/storage/framework/views';

        if (!is_file($pathsToTemplates[0])) {
            throw new \Exception('Templates not exist: '.$pathsToTemplates[0]);
        }
        if (!is_file($pathToCompiledTemplates)) {
            throw new \Exception('Compiled templates not exist: '.$pathToCompiledTemplates);
        }

        // Dependencies
        $filesystem = new Filesystem;
        $eventDispatcher = new Dispatcher(new Container);

        // Create View Factory capable of rendering PHP and Blade templates
        $viewResolver = new EngineResolver;
        $bladeCompiler = new BladeCompiler($filesystem, $pathToCompiledTemplates);

        $viewResolver->register('blade', function () use ($bladeCompiler) {
            return new CompilerEngine($bladeCompiler);
        });

        $viewResolver->register('php', function () {
            return new PhpEngine;
        });

        $viewFinder = new FileViewFinder($filesystem, $pathsToTemplates);
        $viewFactory = new Factory($viewResolver, $viewFinder, $eventDispatcher);

        // Render template
        return $viewFactory->make($viewName, $templateData)->render();
    }
}