<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    public function testRendersTemplate(): void
    {
        $r = new Renderer(__DIR__ . '/../fixtures/templates');
        $html = $r->render('hello', ['name' => 'KUKO']);
        $this->assertStringContainsString('<h1>Hello, KUKO</h1>', $html);
    }

    public function testEscapesData(): void
    {
        $r = new Renderer(__DIR__ . '/../fixtures/templates');
        $html = $r->render('hello', ['name' => '<script>x</script>']);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testMissingTemplateThrows(): void
    {
        $r = new Renderer(__DIR__ . '/../fixtures/templates');
        $this->expectException(\RuntimeException::class);
        $r->render('nonexistent');
    }
}
