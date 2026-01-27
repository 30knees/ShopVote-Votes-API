<?php
/**
 * ShopVote Reviews - XML Parse Exception
 *
 * Exception thrown when XML parsing fails.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Api;

class XmlParseException extends \Exception
{
    /** @var string|null */
    private $rawXml;

    public function __construct(string $message, ?string $rawXml = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->rawXml = $rawXml;
    }

    /**
     * Get the raw XML that failed to parse (truncated for safety)
     */
    public function getRawXml(int $maxLength = 1000): ?string
    {
        if ($this->rawXml === null) {
            return null;
        }

        if (strlen($this->rawXml) > $maxLength) {
            return substr($this->rawXml, 0, $maxLength) . '... [truncated]';
        }

        return $this->rawXml;
    }
}
