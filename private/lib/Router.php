<?php
declare(strict_types=1);
namespace Kuko;

final class RouteMatch
{
    public function __construct(public readonly \Closure $handler, public readonly array $params) {}
}

final class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:\Closure}> */
    private array $routes = [];

    public function get(string $pattern, \Closure $handler): void  { $this->add('GET',  $pattern, $handler); }
    public function post(string $pattern, \Closure $handler): void { $this->add('POST', $pattern, $handler); }

    private function add(string $method, string $pattern, \Closure $handler): void
    {
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler];
    }

    public function match(string $method, string $path): ?RouteMatch
    {
        $path = '/' . trim($path, '/');
        if ($path === '/' && $method === 'GET') {
            foreach ($this->routes as $r) {
                if ($r['method'] === 'GET' && $r['pattern'] === '/') return new RouteMatch($r['handler'], []);
            }
        }
        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) continue;
            $pattern = $r['pattern'] === '/' ? '/' : rtrim($r['pattern'], '/');
            $regex = '#^' . preg_replace('#\{([a-z_][a-z0-9_]*)\}#i', '(?P<$1>[^/]+)', $pattern) . '/?$#';
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                return new RouteMatch($r['handler'], $params);
            }
        }
        return null;
    }
}
