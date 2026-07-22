<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;

class FrontServiceConfigurationTest extends TestCase
{
    public function testFrontContainerLoadsServicesUsedByHooks(): void
    {
        $frontConfig = file_get_contents(__DIR__ . '/../../config/front/services.yml');
        $commonConfig = file_get_contents(__DIR__ . '/../../config/common.yml');

        $this->assertIsString($frontConfig);
        $this->assertStringContainsString('../common.yml', $frontConfig);
        $this->assertIsString($commonConfig);

        foreach ([
            'shopvote.repository.shop_summary',
            'shopvote.repository.review',
            'shopvote.repository.metrics',
            'shopvote.service.sync',
            'shopvote.service.configuration',
            'shopvote.service.ratingstars_parser',
        ] as $serviceId) {
            $this->assertStringContainsString($serviceId . ':', $commonConfig);
        }
    }
}
