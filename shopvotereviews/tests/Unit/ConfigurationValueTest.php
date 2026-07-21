<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Support\ConfigurationValue;

class ConfigurationValueTest extends TestCase
{
    public function testPreservesDocumentedZeroValue(): void
    {
        $this->assertSame(0, ConfigurationValue::integer('0', 365));
        $this->assertSame(0, ConfigurationValue::integer(0, 200));
    }

    public function testUsesDefaultOnlyForMissingValue(): void
    {
        $this->assertSame(365, ConfigurationValue::integer(false, 365));
        $this->assertSame(200, ConfigurationValue::integer('', 200));
    }
}
