<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression: a public page template that has its own seo.{type}.* settings
 * MUST set $pageType, otherwise Seo::resolve falls back to 'default' and
 * overrides the page's own title/description with the generic site title
 * (duplicate <title> across pages — bad SEO).
 */
final class PageSeoTypeTest extends TestCase
{
    /** @dataProvider pages */
    public function testPageDeclaresPageType(string $file, string $expectedType): void
    {
        $src = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/pages/' . $file);
        $this->assertMatchesRegularExpression(
            '/\$pageType\s*=\s*[\'"]' . preg_quote($expectedType, '/') . '[\'"]\s*;/',
            $src,
            "$file must set \$pageType = '$expectedType' so Seo::resolve uses its own seo.$expectedType.* settings"
        );
    }

    public static function pages(): array
    {
        return [
            ['home.php', 'home'],
            ['reservation.php', 'rezervacia'],
            ['gallery.php', 'gallery'],
            ['faq.php', 'faq'],
            ['privacy.php', 'privacy'],
        ];
    }
}
