<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Repository\MetricsRepository;

class MetricsRepositoryTest extends TestCase
{
    public function testRejectsNonAllowlistedEventBeforeDatabaseAccess(): void
    {
        $this->assertFalse((new MetricsRepository())->increment('customer_email', 'homepage'));
    }

    public function testRejectsNonAllowlistedPlacementBeforeDatabaseAccess(): void
    {
        $this->assertFalse((new MetricsRepository())->increment('widget_view', 'arbitrary'));
    }
}
