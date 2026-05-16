<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class LightboxThumbsF3Test extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function testGalleryJsBuildsThumbStripWithActiveSync(): void
    {
        $js = file_get_contents($this->root() . '/public/assets/js/gallery.js');
        $this->assertStringContainsString('lightbox__thumb', $js, 'gallery.js must build the thumbnail strip');
        $this->assertStringContainsString('is-active', $js, 'gallery.js must toggle an active-thumb class');
        $this->assertStringContainsString('aria-current', $js, 'gallery.js must sync aria-current on the active thumb');
    }

    public function testGalleryJsUsesSvgChevronArrows(): void
    {
        $js = file_get_contents($this->root() . '/public/assets/js/gallery.js');
        $this->assertStringContainsString('<svg', $js, 'gallery.js must use inline SVG icons');
        $this->assertStringContainsString('lightbox__btn--prev', $js, 'prev button must still be constructed');
        $this->assertStringContainsString('lightbox__btn--next', $js, 'next button must still be constructed');
    }

    public function testCssHasThumbStripStyles(): void
    {
        $css = file_get_contents($this->root() . '/public/assets/css/main.css');
        $this->assertStringContainsString('.lightbox__thumbs', $css, 'main.css must style the thumbnail strip');
        $this->assertStringContainsString('.lightbox__thumb', $css, 'main.css must style individual thumbnails');
    }
}
