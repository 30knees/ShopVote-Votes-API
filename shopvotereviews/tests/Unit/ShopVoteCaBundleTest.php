<?php

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ShopVoteCaBundleTest extends TestCase
{
    public function testContainsExpectedDigiCertChain(): void
    {
        $bundle = file_get_contents(__DIR__ . '/../../resources/certs/shopvote-ca-chain.pem');

        $this->assertIsString($bundle);
        preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $bundle, $matches);
        $this->assertCount(2, $matches[0]);

        $fingerprints = array_map(
            static fn(string $certificate): string => strtoupper((string) openssl_x509_fingerprint($certificate, 'sha256')),
            $matches[0]
        );

        $this->assertSame([
            '4BCC5E234FE81EDE4EAF883AA19C31335B0B26E85E066B9945E4CB6153EB20C2',
            'CB3CCBB76031E5E0138F8DD39A23F9DE47FFC35E43C1144CEA27D46A5AB1CB5F',
        ], $fingerprints);
    }
}
