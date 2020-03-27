<?php

namespace lampheart\Providers;

use lampheart\Support\Container;
use Phroute\Phroute\HandlerResolver;
use Phroute\Phroute\HandlerResolverInterface;
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

            $routerPath = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))).'/routes/web.php';

            if (!is_file($routerPath)) {
                throw new \Exception('Routes not exist: '.$routerPath);
            }

            $router = new lanterRouteCollector;
            require_once $routerPath;

            $dispatcher = new LanternDispatcher($router->getData());
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

class LanternHandlerResolver extends HandlerResolver
{
    use Container;

    /**
     * Create an instance of the given handler.
     *
     * @param $handler
     * @return array
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function resolve ($handler)
    {
        if(is_array($handler) && is_string($handler[0]))
        {
            $handler[0] = $this->container($handler[0]);
        }

        return $handler;
    }
}

class LanternDispatcher extends Dispatcher
{
    protected $staticRouteMap;
    protected $variableRouteData;
    protected $filters;
    protected $handlerResolver;

    /**
     * Create a new route dispatcher.
     *
     * @param $data
     * @param HandlerResolverInterface $resolver
     */
    public function __construct($data, HandlerResolverInterface $resolver = null)
    {
        $this->staticRouteMap = $data->getStaticRoutes();

        $this->variableRouteData = $data->getVariableRoutes();

        $this->filters = $data->getFilters();

        if ($resolver === null)
        {
            $this->handlerResolver = new LanternHandlerResolver();
        }
        else
        {
            $this->handlerResolver = $resolver;
        }
    }

    /**
     * Dispatch a route for the given HTTP Method / URI.
     *
     * @param $httpMethod
     * @param $uri
     * @return mixed|null
     * @throws HttpMethodNotAllowedException
     * @throws HttpRouteNotFoundException
     */
    public function dispatch($httpMethod, $uri)
    {
        list($handler, $filters, $vars) = $this->dispatchRoute($httpMethod, trim($uri, '/'));

        list($beforeFilter, $afterFilter) = $this->parseFilters($filters);

        if(($response = $this->dispatchFilters($beforeFilter)) !== null)
        {
            return $response;
        }

        $resolvedHandler = $this->handlerResolver->resolve($handler);

        $response = call_user_func_array($resolvedHandler, $vars);

        return $this->dispatchFilters($afterFilter, $response);
    }

    /**
     * Dispatch a route filter.
     *
     * @param $filters
     * @param null $response
     * @return mixed|null
     */
    private function dispatchFilters($filters, $response = null)
    {
        while($filter = array_shift($filters))
        {
            $handler = $this->handlerResolver->resolve($filter);

            if(($filteredResponse = call_user_func($handler, $response)) !== null)
            {
                return $filteredResponse;
            }
        }

        return $response;
    }

    /**
     * Normalise the array filters attached to the route and merge with any global filters.
     *
     * @param $filters
     * @return array
     */
    private function parseFilters($filters)
    {
        $beforeFilter = array();
        $afterFilter = array();

        if(isset($filters[Route::BEFORE]))
        {
            $beforeFilter = array_intersect_key($this->filters, array_flip((array) $filters[Route::BEFORE]));
        }

        if(isset($filters[Route::AFTER]))
        {
            $afterFilter = array_intersect_key($this->filters, array_flip((array) $filters[Route::AFTER]));
        }

        return array($beforeFilter, $afterFilter);
    }

    /**
     * Perform the route dispatching. Check static routes first followed by variable routes.
     *
     * @param $httpMethod
     * @param $uri
     * @return mixed
     * @throws HttpMethodNotAllowedException
     * @throws HttpRouteNotFoundException
     */
    private function dispatchRoute($httpMethod, $uri)
    {
        if (isset($this->staticRouteMap[$uri]))
        {
            return $this->dispatchStaticRoute($httpMethod, $uri);
        }

        return $this->dispatchVariableRoute($httpMethod, $uri);
    }

    /**
     * Handle the dispatching of static routes.
     *
     * @param $httpMethod
     * @param $uri
     * @return mixed
     * @throws HttpMethodNotAllowedException
     */
    private function dispatchStaticRoute($httpMethod, $uri)
    {
        $routes = $this->staticRouteMap[$uri];

        if (!isset($routes[$httpMethod]))
        {
            $httpMethod = $this->checkFallbacks($routes, $httpMethod);
        }

        return $routes[$httpMethod];
    }

    /**
     * Check fallback routes: HEAD for GET requests followed by the ANY attachment.
     *
     * @param $routes
     * @param $httpMethod
     * @return mixed
     * @throws HttpMethodNotAllowedException
     */
    private function checkFallbacks($routes, $httpMethod)
    {
        $additional = array(Route::ANY);

        if($httpMethod === Route::HEAD)
        {
            $additional[] = Route::GET;
        }

        foreach($additional as $method)
        {
            if(isset($routes[$method]))
            {
                return $method;
            }
        }

        $this->matchedRoute = $routes;

        throw new HttpMethodNotAllowedException('Allow: ' . implode(', ', array_keys($routes)));
    }

    /**
     * Handle the dispatching of variable routes.
     *
     * @param $httpMethod
     * @param $uri
     * @return mixed
     * @throws HttpMethodNotAllowedException
     * @throws HttpRouteNotFoundException
     */
    private function dispatchVariableRoute($httpMethod, $uri)
    {
        foreach ($this->variableRouteData as $data)
        {
            if (!preg_match($data['regex'], $uri, $matches))
            {
                continue;
            }

            $count = count($matches);

            while(!isset($data['routeMap'][$count++]));

            $routes = $data['routeMap'][$count - 1];

            if (!isset($routes[$httpMethod]))
            {
                $httpMethod = $this->checkFallbacks($routes, $httpMethod);
            }

            foreach (array_values($routes[$httpMethod][2]) as $i => $varName)
            {
                if(!isset($matches[$i + 1]) || $matches[$i + 1] === '')
                {
                    unset($routes[$httpMethod][2][$varName]);
                }
                else
                {
                    $routes[$httpMethod][2][$varName] = $matches[$i + 1];
                }
            }

            return $routes[$httpMethod];
        }

        throw new HttpRouteNotFoundException('Route ' . $uri . ' does not exist');
    }
}
