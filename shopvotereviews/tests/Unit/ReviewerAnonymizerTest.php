<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShopVote\ShopVoteReviews\Support\ReviewerAnonymizer;

class ReviewerAnonymizerTest extends TestCase
{
    public function testAnonymizesMultibyteFirstNameByCharacters(): void
    {
        $this->assertSame('Ä***', ReviewerAnonymizer::anonymize('Änne Beispiel'));
        $this->assertSame('李*', ReviewerAnonymizer::anonymize('李雷'));
    }

    public function testUsesLocalizedAnonymousLabel(): void
    {
        $this->assertSame('Anonym', ReviewerAnonymizer::anonymize(' ', 'Anonym'));
    }
}
