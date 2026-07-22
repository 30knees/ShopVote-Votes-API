<?php
/**
 * ShopVote Reviews - XML Parser
 *
 * Parses XML responses from the ShopVote VotesAPI.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Api;

use SimpleXMLElement;
use ShopVote\ShopVoteReviews\Security\ShopVoteUrlValidator;

class XmlParser
{
    private const MAX_XML_BYTES = 2097152;

    /**
     * Parse API response XML
     *
     * @param string $xml Raw XML string
     *
     * @return ParsedResponse
     *
     * @throws XmlParseException
     */
    public function parse(string $xml): ParsedResponse
    {
        if (strlen($xml) > self::MAX_XML_BYTES) {
            throw new XmlParseException('XML response exceeded the 2 MiB limit.');
        }

        if (preg_match('/<!DOCTYPE\b/i', $xml)) {
            throw new XmlParseException('XML document types are not allowed.');
        }

        libxml_use_internal_errors(true);

        $simpleXml = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);

        if ($simpleXml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $errorMessages = array_map(
                fn($error) => trim($error->message),
                $errors
            );

            throw new XmlParseException(
                'Failed to parse XML: ' . implode('; ', $errorMessages),
                $xml
            );
        }

        libxml_clear_errors();

        return $this->extractData($simpleXml);
    }

    /**
     * Extract data from parsed XML
     */
    private function extractData(SimpleXMLElement $xml): ParsedResponse
    {
        $response = new ParsedResponse();

        // Extract shop profile data
        $response->shopId = $this->extractString($xml, 'shopid', 64);
        $response->shopName = $this->extractString($xml, 'name', 255);
        $response->profileUrl = $this->extractShopVoteUrl($xml, 'profile');
        $response->shopUrl = $this->extractHttpUrl($xml, 'shopurl', 512);
        $response->lastVote = $this->extractDateTime($xml, 'last_vote');

        // Extract rating summary (if present)
        if (isset($xml->rating_summary)) {
            $summary = $xml->rating_summary;

            // ShopVote documents repeated rating_value elements identified by a type attribute.
            if (isset($summary->rating_value)) {
                foreach ($summary->rating_value as $ratingValue) {
                    $type = strtolower(trim((string) $ratingValue['type']));
                    if ($type === 'stars') {
                        $response->ratingValueStars = $this->parseBoundedFloat((string) $ratingValue, 1.0, 5.0);
                    } elseif ($type === 'score') {
                        $response->ratingValueScore = $this->parseBoundedFloat((string) $ratingValue, 1.0, 5.0);
                    } elseif ($type === 'word') {
                        $response->ratingWord = $this->truncateUtf8(trim((string) $ratingValue), 64);
                    }
                }

                // Retain compatibility with nested rating_value responses.
                $ratingValue = $summary->rating_value[0];
                $response->ratingValueStars ??= $this->extractRating($ratingValue, 'stars');
                $response->ratingValueScore ??= $this->extractBoundedFloat($ratingValue, 'score', 1.0, 5.0);
                $response->ratingWord ??= $this->extractString($ratingValue, 'word', 64);
            }

            $response->ratingsCount = $this->extractNonNegativeInt($summary, 'ratings_count');
            $response->ratingsPositive = $this->extractNonNegativeInt($summary, 'ratings_positive');
            $response->ratingsNeutral = $this->extractNonNegativeInt($summary, 'ratings_neutral');
            $response->ratingsNegative = $this->extractNonNegativeInt($summary, 'ratings_negative');
            $response->commentsCount = $this->extractNonNegativeInt($summary, 'comments_count');
            $response->hasSummary = $response->ratingValueStars !== null;
        }

        // Extract reviews (if present)
        if (isset($xml->reviews) && isset($xml->reviews->review)) {
            foreach ($xml->reviews->review as $reviewXml) {
                $review = $this->parseReview($reviewXml);
                if ($review !== null) {
                    $response->reviews[] = $review;
                }
            }

            $response->hasReviews = $response->reviews !== [];
        }

        return $response;
    }

    /**
     * Parse a single review element
     */
    private function parseReview(SimpleXMLElement $reviewXml): ?ParsedReview
    {
        // Get review ID from attribute
        $reviewId = null;
        if (isset($reviewXml['id'])) {
            $reviewId = (string) $reviewXml['id'];
        }

        if (empty($reviewId) || strlen($reviewId) > 64 || preg_match('/[\x00-\x1F\x7F]/', $reviewId)) {
            return null;
        }

        $review = new ParsedReview();
        $review->reviewId = $reviewId;

        $verified = $this->extractString($reviewXml, 'isVerified');
        if ($verified === null && isset($reviewXml['isVerified'])) {
            $verified = trim((string) $reviewXml['isVerified']);
        }
        if ($verified !== null) {
            $review->isVerified = strtolower($verified) === 'true' || $verified === '1';
        }

        $review->reviewUrl = $this->extractShopVoteUrl($reviewXml, 'review_url');
        $review->reviewDate = $this->extractDateTime($reviewXml, 'review_date');
        $review->reviewer = $this->extractString($reviewXml, 'reviewer', 255);
        $review->reviewText = $this->extractString($reviewXml, 'text', 65535);

        // Rating can be in different formats
        if (isset($reviewXml->review_rating)) {
            $rating = $reviewXml->review_rating;
            $review->reviewRatingStars = $this->extractRating($rating, 'stars');

            // If stars not present, try to get the value directly
            if ($review->reviewRatingStars === null) {
                $ratingValue = (string) $rating;
                if (is_numeric($ratingValue)) {
                    $numericRating = (float) $ratingValue;
                    if ($numericRating >= 1.0 && $numericRating <= 5.0) {
                        $review->reviewRatingStars = $numericRating;
                    }
                }
            }
        }

        // Parse review answers
        $answerNodes = null;
        if (isset($reviewXml->review_answers->answer)) {
            $answerNodes = $reviewXml->review_answers->answer;
        } elseif (isset($reviewXml->answers->answer)) {
            $answerNodes = $reviewXml->answers->answer;
        }

        if ($answerNodes !== null) {
            foreach ($answerNodes as $answerXml) {
                $answer = $this->parseAnswer($answerXml);
                if ($answer !== null) {
                    $review->answers[] = $answer;
                }
            }
        }

        return $review;
    }

    /**
     * Parse a review answer element
     */
    private function parseAnswer(SimpleXMLElement $answerXml): ?ParsedAnswer
    {
        $answer = new ParsedAnswer();

        $type = $this->extractString($answerXml, 'type', 32)
            ?? $this->extractString($answerXml, 'author', 32);
        if ($type === null && isset($answerXml['type'])) {
            $type = trim((string) $answerXml['type']);
        }
        if ($type === null && isset($answerXml['author'])) {
            $type = trim((string) $answerXml['author']);
        }

        $type = strtolower($type ?? '');
        $answer->type = $type === 'shop' ? 'Shop' : ($type === 'kunde' ? 'Kunde' : 'Unknown');

        $answer->date = $this->extractDateTime($answerXml, 'date');
        $answer->text = $this->extractString($answerXml, 'text', 65535);

        // If text is directly in the element
        if (empty($answer->text)) {
            $answer->text = $this->truncateUtf8(trim((string) $answerXml), 65535);
        }

        return $answer;
    }

    /**
     * Extract a string value from XML element
     */
    private function extractString(SimpleXMLElement $xml, string $key, ?int $maxBytes = null): ?string
    {
        if (!isset($xml->$key)) {
            return null;
        }

        $value = trim((string) $xml->$key);

        if ($value === '') {
            return null;
        }

        return $maxBytes === null ? $value : $this->truncateUtf8($value, $maxBytes);
    }

    /**
     * Extract an integer value from XML element
     */
    private function extractInt(SimpleXMLElement $xml, string $key): ?int
    {
        $value = $this->extractString($xml, $key);

        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function extractNonNegativeInt(SimpleXMLElement $xml, string $key): ?int
    {
        if (!isset($xml->$key)) {
            return null;
        }

        $raw = trim((string) $xml->$key);
        if (!preg_match('/^\d{1,10}$/', $raw) || (float) $raw > 4294967295) {
            return null;
        }

        $value = (int) $raw;

        return $value !== null && $value >= 0 ? $value : null;
    }

    /**
     * Extract a float value from XML element
     */
    private function extractFloat(SimpleXMLElement $xml, string $key): ?float
    {
        $value = $this->extractString($xml, $key);

        if ($value === null) {
            return null;
        }

        // Handle German decimal format (comma as separator)
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function extractRating(SimpleXMLElement $xml, string $key): ?float
    {
        $value = $this->extractFloat($xml, $key);

        return $value !== null && $value >= 1.0 && $value <= 5.0 ? $value : null;
    }

    private function extractBoundedFloat(SimpleXMLElement $xml, string $key, float $minimum, float $maximum): ?float
    {
        $value = $this->extractFloat($xml, $key);

        return $value !== null && $value >= $minimum && $value <= $maximum ? $value : null;
    }

    private function parseBoundedFloat(string $value, float $minimum, float $maximum): ?float
    {
        $value = str_replace(',', '.', trim($value));
        if (!is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return $value >= $minimum && $value <= $maximum ? $value : null;
    }

    private function extractShopVoteUrl(SimpleXMLElement $xml, string $key): ?string
    {
        $url = $this->extractString($xml, $key);

        if ($url !== null && strlen($url) > 512) {
            return null;
        }

        return ShopVoteUrlValidator::normalize($url);
    }

    private function extractHttpUrl(SimpleXMLElement $xml, string $key, int $maxBytes): ?string
    {
        $url = $this->extractString($xml, $key);
        if ($url === null || strlen($url) > $maxBytes || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);

        $scheme = strtolower($parts['scheme'] ?? '');
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        return in_array($scheme, ['http', 'https'], true)
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && ($port === null || ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
            ? $url
            : null;
    }

    private function truncateUtf8(string $value, int $maxBytes): string
    {
        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        return mb_strcut($value, 0, $maxBytes, 'UTF-8');
    }

    /**
     * Extract a datetime value from XML element
     */
    private function extractDateTime(SimpleXMLElement $xml, string $key): ?\DateTime
    {
        $value = $this->extractString($xml, $key);

        if ($value === null) {
            return null;
        }

        try {
            // Try multiple date formats
            $formats = [
                'Y-m-d H:i:s',
                'Y-m-d\TH:i:s',
                'Y-m-d\TH:i:sP',
                'Y-m-d',
                'd.m.Y H:i:s',
                'd.m.Y',
            ];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            }

            // Try strtotime as fallback
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return new \DateTime('@' . $timestamp);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
