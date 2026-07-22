<?php

declare(strict_types=1);

namespace {
    if (!class_exists('Configuration', false)) {
        class Configuration
        {
            public static array $values = [];
            public static array $htmlFlags = [];

            public static function get(string $key): mixed
            {
                return self::$values[$key] ?? false;
            }

            public static function updateValue(
                string $key,
                mixed $value,
                bool $html = false,
                mixed $idShopGroup = null,
                mixed $idShop = null
            ): bool {
                self::$values[$key] = $value;
                self::$htmlFlags[$key] = $html;

                return true;
            }
        }
    }
}

namespace ShopVote\ShopVoteReviews\Tests\Unit {
    use PHPUnit\Framework\Attributes\PreserveGlobalState;
    use PHPUnit\Framework\Attributes\RunInSeparateProcess;
    use PHPUnit\Framework\TestCase;
    use ShopVote\ShopVoteReviews\Service\ConfigurationService;
    use ShopVote\ShopVoteReviews\Service\EasyReviewsSnippetParser;
    use ShopVote\ShopVoteReviews\Service\RatingStarsSnippetParser;

    class EasyReviewsPersistenceTest extends TestCase
    {
        protected function setUp(): void
        {
            \Configuration::$values = [];
            \Configuration::$htmlFlags = [];
        }

        #[RunInSeparateProcess]
        #[PreserveGlobalState(false)]
        public function testPersistsValidatedSourceSnippetsForEditing(): void
        {
            $this->defineModuleConfigurationStub();

            $html = '<div><span>CUSTOMERMAIL</span><span>ORDERNUMBER</span></div>';
            $javascript = <<<'HTML'
<script src="https://feedback.shopvote.de/srt-v4.min.js"></script>
<script>var myToken = "persistTest_123456"; var myLanguage = "DE"; loadSRT(myToken, "https");</script>
HTML;

            $service = new ConfigurationService(new EasyReviewsSnippetParser(), new RatingStarsSnippetParser());
            $service->importEasyReviewsSnippets($html, $javascript);

            $this->assertSame('base64:' . base64_encode($html), \Configuration::$values['SHOPVOTE_EASYREVIEWS_HTML_CODE']);
            $this->assertSame('base64:' . base64_encode($javascript), \Configuration::$values['SHOPVOTE_EASYREVIEWS_JAVASCRIPT_CODE']);
            $this->assertFalse(\Configuration::$htmlFlags['SHOPVOTE_EASYREVIEWS_HTML_CODE']);
            $this->assertFalse(\Configuration::$htmlFlags['SHOPVOTE_EASYREVIEWS_JAVASCRIPT_CODE']);
            $this->assertSame('https://feedback.shopvote.de/srt-v4.min.js', \Configuration::$values['SHOPVOTE_EASYREVIEWS_SCRIPT_URL']);
            $this->assertSame('persistTest_123456', \Configuration::$values['SHOPVOTE_EASYREVIEWS_TOKEN']);

            $config = $service->getAll();
            $this->assertSame($html, $config['EASYREVIEWS_HTML_CODE']);
            $this->assertSame($javascript, $config['EASYREVIEWS_JAVASCRIPT_CODE']);
        }

        #[RunInSeparateProcess]
        #[PreserveGlobalState(false)]
        public function testDoesNotPersistInvalidSourceSnippets(): void
        {
            $this->defineModuleConfigurationStub();

            $service = new ConfigurationService(new EasyReviewsSnippetParser(), new RatingStarsSnippetParser());

            try {
                $service->importEasyReviewsSnippets('<div></div>', '<script src="https://evil.example/code.js"></script>');
                $this->fail('Invalid EasyReviews code should be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertSame([], \Configuration::$values);
            }
        }

        #[RunInSeparateProcess]
        #[PreserveGlobalState(false)]
        public function testPersistsValidatedRatingStarsSourceForEditing(): void
        {
            $this->defineModuleConfigurationStub();

            $snippet = <<<'HTML'
<script src="https://widgets.shopvote.de/js/reputation-badge-v2.min.js"></script>
<script>var myShopID=26444;var myBadgetType=1;var mySrc="https";createRBadge(myShopID,myBadgetType,mySrc);</script>
HTML;

            $service = new ConfigurationService(new EasyReviewsSnippetParser(), new RatingStarsSnippetParser());
            $service->importRatingStarsSnippet($snippet);

            $this->assertSame(
                'base64:' . base64_encode($snippet),
                \Configuration::$values['SHOPVOTE_RATINGSTARS_CODE']
            );
            $this->assertFalse(\Configuration::$htmlFlags['SHOPVOTE_RATINGSTARS_CODE']);
            $this->assertSame($snippet, $service->getAll()['RATINGSTARS_CODE']);
        }

        #[RunInSeparateProcess]
        #[PreserveGlobalState(false)]
        public function testDoesNotPersistInvalidRatingStarsSource(): void
        {
            $this->defineModuleConfigurationStub();

            $service = new ConfigurationService(new EasyReviewsSnippetParser(), new RatingStarsSnippetParser());

            try {
                $service->importRatingStarsSnippet('<script src="https://evil.example/code.js"></script>');
                $this->fail('Invalid RatingStars code should be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertSame([], \Configuration::$values);
            }
        }

        private function defineModuleConfigurationStub(): void
        {
            if (class_exists('ShopVoteReviews', false)) {
                return;
            }

            eval(<<<'PHP'
class ShopVoteReviews
{
    public const CONFIG_KEYS = [
        'EASYREVIEWS_SCRIPT_URL' => 'SHOPVOTE_EASYREVIEWS_SCRIPT_URL',
        'EASYREVIEWS_TOKEN' => 'SHOPVOTE_EASYREVIEWS_TOKEN',
        'EASYREVIEWS_OPTIONS' => 'SHOPVOTE_EASYREVIEWS_OPTIONS',
        'EASYREVIEWS_HTML_CODE' => 'SHOPVOTE_EASYREVIEWS_HTML_CODE',
        'EASYREVIEWS_JAVASCRIPT_CODE' => 'SHOPVOTE_EASYREVIEWS_JAVASCRIPT_CODE',
        'RATINGSTARS_CODE' => 'SHOPVOTE_RATINGSTARS_CODE',
    ];

    public static function maskApiKey(string $value): string
    {
        return $value;
    }
}
PHP);
        }
    }
}
