<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class HeroResponsiveTest extends TestCase
{
    public function testMobileHeroVariantsExist(): void
    {
        $d = \dirname(__DIR__, 3) . '/public/assets/img/';
        foreach (['hero-768.webp','hero-768.jpg'] as $f) {
            $this->assertFileExists($d . $f, "missing $f");
            [$w] = getimagesize($d . $f);
            $this->assertSame(768, $w, "$f must be 768px wide");
        }
        $this->assertLessThan(
            filesize($d . 'hero.webp'),
            filesize($d . 'hero-768.webp'),
            'mobile webp must be smaller than full hero.webp'
        );
    }
    public function testHeroAssetsHaveCorrectImageType(): void
    {
        $d = \dirname(__DIR__, 3) . '/public/assets/img/';
        $expect = [
            'hero.jpg'       => IMAGETYPE_JPEG,
            'hero-768.jpg'   => IMAGETYPE_JPEG,
            'hero.webp'      => IMAGETYPE_WEBP,
            'hero-768.webp'  => IMAGETYPE_WEBP,
        ];
        foreach ($expect as $file => $type) {
            $info = getimagesize($d . $file);
            $this->assertNotFalse($info, "$file is not a readable image");
            $this->assertSame(
                $type,
                $info[2],
                "$file content must match its extension (no PNG-as-.jpg footgun)"
            );
        }
    }

    public function testCssHasMobileHeroOverride(): void
    {
        $css = file_get_contents(\dirname(__DIR__, 3) . '/public/assets/css/main.css');
        $this->assertStringContainsString('hero-768.webp', $css, 'main.css must reference mobile hero variant');
        $this->assertMatchesRegularExpression('/@media[^{]*max-width[^{]*\b768px/', $css, 'main.css needs a max-width:768px media query');
    }
    public function testHeadPreloadIsViewportConditional(): void
    {
        $h = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/head.php');
        $this->assertStringContainsString('hero-768.webp', $h, 'head.php should preload the mobile variant conditionally');
        $this->assertStringContainsString('media=', $h, 'head.php hero preload must be media-conditional');
    }
}
