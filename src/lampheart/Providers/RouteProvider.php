<?php

namespace lampheart\Providers;

use Phroute\Phroute\RouteCollector;
use Phroute\Phroute\Dispatcher;
use Phroute\Phroute\Exception\HttpRouteNotFoundException;
use Phroute\Phroute\Exception\HttpMethodNotAllowedException;
use Phroute\Phroute\Route;
use Exception;
use lampheart\Support\Http\Response;

class RouteProvider
{
    public function __construct()
    {
        $this->make();
    }

    public function make()
    {
        try
        {
            if (is_cli_request()) {
                return;
            }

            $routerPath = dirname(dirname(dirname(dirname(__DIR__)))).'/routes/web.php';

            if (!file_exists($routerPath)) {
                throw new \Exception('Routes not exist: '.$routerPath);
            }

            $router = new lanterRouteCollector;
            require_once $routerPath;

            $dispatcher = new Dispatcher($router->getData());
            $response   = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
            if (is_null($response))
            {
                header("HTTP/1.0 404 Not Found");
                echo Response::json('404 Not Found');
                exit();
            }
            echo $response;
        }
        catch (HttpRouteNotFoundException $e)
        {
            header("HTTP/1.0 404 Not Found");
            throw new Exception('404 Not Found');
        }
        catch (HttpMethodNotAllowedException $e)
        {
            header("HTTP/1.0 405 Not Allowed");
            throw new Exception('405 Not Allowed');
        }
        catch (Exception $e)
        {
            header("HTTP/1.0 500 Internal Server Error");
            if (env('APP_DEBUG') === true)
            {
                throw new Exception($e);
            }
            else
            {
                throw new Exception('500 Internal Server Error');
            }
        }
    }
}

class lanterRouteCollector extends RouteCollector
{
    private $defaultHandler = 'App\\Http\\Controllers\\';

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function get($route, $handler, array $filters = [])
    {
        if (is_array($handler)) {
            $this->customHandler($handler);
        }
        return $this->addRoute(Route::GET, $route, $handler, $filters);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function post($route, $handler, array $filters = [])
    {
        if (is_array($handler)) {
            $this->customHandler($handler);
        }
        return $this->addRoute(Route::POST, $route, $handler, $filters);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function put($route, $handler, array $filters = [])
    {
        if (is_array($handler)) {
            $this->customHandler($handler);
        }
        return $this->addRoute(Route::PUT, $route, $handler, $filters);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function patch($route, $handler, array $filters = [])
    {
        if (is_array($handler)) {
            $this->customHandler($handler);
        }
        return $this->addRoute(Route::PATCH, $route, $handler, $filters);
    }

    /**
     * @param $route
     * @param $handler
     * @param array $filters
     * @return RouteCollector
     */
    public function delete($route, $handler, array $filters = [])
    {
        if (is_array($handler)) {
            $this->customHandler($handler);
        }
        return $this->addRoute(Route::DELETE, $route, $handler, $filters);
    }

    private function customHandler(array &$handler)
    {
        $handler[0] = $this->defaultHandler.$handler[0];
    }
}