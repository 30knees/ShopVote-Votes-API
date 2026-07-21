<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Service\SyncResult;

class SyncResultTest extends TestCase
{
    public function testExposesPartialWarningsAndComponentStatus(): void
    {
        $result = new SyncResult();
        $result->success = true;
        $result->partial = true;
        $result->warnings = ['Summary unavailable'];
        $result->components['summary'] = 'failed';
        $result->components['reviews'] = 'success';

        $data = $result->toArray();

        $this->assertTrue($data['partial']);
        $this->assertSame(['Summary unavailable'], $data['warnings']);
        $this->assertSame('failed', $data['components']['summary']);
        $this->assertSame('success', $data['components']['reviews']);
    }
}
