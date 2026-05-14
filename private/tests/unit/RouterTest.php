<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testStaticRoute(): void
    {
        $r = new Router();
        $r->get('/', fn() => 'home');
        $match = $r->match('GET', '/');
        $this->assertNotNull($match);
        $this->assertSame('home', ($match->handler)());
    }

    public function testParamRoute(): void
    {
        $r = new Router();
        $r->get('/admin/reservation/{id}', fn($params) => 'detail-' . $params['id']);
        $match = $r->match('GET', '/admin/reservation/42');
        $this->assertSame('detail-42', ($match->handler)($match->params));
    }

    public function testNoMatch(): void
    {
        $r = new Router();
        $r->get('/', fn() => 'home');
        $this->assertNull($r->match('GET', '/missing'));
    }

    public function testMethodMismatch(): void
    {
        $r = new Router();
        $r->get('/', fn() => 'home');
        $this->assertNull($r->match('POST', '/'));
    }

    public function testTrailingSlashIgnored(): void
    {
        $r = new Router();
        $r->get('/ochrana-udajov', fn() => 'p');
        $this->assertNotNull($r->match('GET', '/ochrana-udajov/'));
    }

    public function testPostRoute(): void
    {
        $r = new Router();
        $r->post('/api/x', fn() => 'posted');
        $this->assertNotNull($r->match('POST', '/api/x'));
        $this->assertNull($r->match('GET', '/api/x'));
    }
}
