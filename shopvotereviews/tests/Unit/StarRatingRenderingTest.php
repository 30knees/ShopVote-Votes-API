<?php

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Core\Module {
    if (!interface_exists(WidgetInterface::class)) {
        interface WidgetInterface
        {
            public function renderWidget($hookName, array $configuration);

            public function getWidgetVariables($hookName, array $configuration);
        }
    }
}

namespace {
    if (!defined('_PS_VERSION_')) {
        define('_PS_VERSION_', '8.2.1');
    }

    if (!class_exists('Module', false)) {
        class Module
        {
            public function trans(string $id, array $parameters = []): string
            {
                return strtr($id, $parameters);
            }
        }
    }

    require_once __DIR__ . '/../../shopvotereviews.php';
}

namespace ShopVote\ShopVoteReviews\Tests\Unit {
    use PHPUnit\Framework\TestCase;

    class StarRatingRenderingTest extends TestCase
    {
        public function testRendersDecimalStringReturnedByPrestaShopDatabase(): void
        {
            $reflection = new \ReflectionClass(\ShopVoteReviews::class);
            $module = $reflection->newInstanceWithoutConstructor();
            $method = $reflection->getMethod('generateStarsHtml');

            $html = $method->invoke($module, '4.75');

            $this->assertStringContainsString('Rating: 4.8 out of 5 stars', $html);
            $this->assertSame(4, substr_count($html, 'shopvote-star-full'));
            $this->assertSame(1, substr_count($html, 'shopvote-star-half'));
            $this->assertSame(0, substr_count($html, 'shopvote-star-empty'));
        }
    }
}
