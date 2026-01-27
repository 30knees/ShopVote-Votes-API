<?php
/**
 * ShopVote Reviews - API Response
 *
 * Value object representing an API response.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Api;

class ApiResponse
{
    /** @var bool */
    private $success;

    /** @var int */
    private $httpCode;

    /** @var string|null */
    private $body;

    /** @var string|null */
    private $error;

    public function __construct(
        bool $success,
        int $httpCode,
        ?string $body,
        ?string $error
    ) {
        $this->success = $success;
        $this->httpCode = $httpCode;
        $this->body = $body;
        $this->error = $error;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Check if the response indicates a permission/access error
     */
    public function isPermissionError(): bool
    {
        return in_array($this->httpCode, [400, 401, 403], true);
    }

    /**
     * Check if the response is a server error
     */
    public function isServerError(): bool
    {
        return $this->httpCode >= 500 && $this->httpCode < 600;
    }
}
