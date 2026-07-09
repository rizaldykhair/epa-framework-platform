<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler', 'middleware');
    }

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $route['pattern']);
            if (preg_match('#^' . $regex . '$#', $request->path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                foreach ($route['middleware'] as $middleware) {
                    $middleware($request, $params);
                }
                [$class, $method] = $route['handler'];
                (new $class())->$method($request, $params);
                return;
            }
        }
        Response::error('Route not found', 404);
    }
}
