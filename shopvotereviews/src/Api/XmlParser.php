<?php
/**
 * ShopVote Reviews - XML Parser
 *
 * Parses XML responses from the ShopVote VotesAPI.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Api;

use SimpleXMLElement;

class XmlParser
{
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
        libxml_use_internal_errors(true);

        $simpleXml = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

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

        return $this->extractData($simpleXml);
    }

    /**
     * Extract data from parsed XML
     */
    private function extractData(SimpleXMLElement $xml): ParsedResponse
    {
        $response = new ParsedResponse();

        // Extract shop profile data
        $response->shopId = $this->extractString($xml, 'shopid');
        $response->shopName = $this->extractString($xml, 'name');
        $response->profileUrl = $this->extractString($xml, 'profile');
        $response->shopUrl = $this->extractString($xml, 'shopurl');
        $response->lastVote = $this->extractDateTime($xml, 'last_vote');

        // Extract rating summary (if present)
        if (isset($xml->rating_summary)) {
            $summary = $xml->rating_summary;
            $response->hasSummary = true;

            // Rating value can have different formats
            if (isset($summary->rating_value)) {
                $ratingValue = $summary->rating_value;
                $response->ratingValueStars = $this->extractFloat($ratingValue, 'stars');
                $response->ratingValueScore = $this->extractFloat($ratingValue, 'score');
                $response->ratingWord = $this->extractString($ratingValue, 'word');
            }

            $response->ratingsCount = $this->extractInt($summary, 'ratings_count');
            $response->ratingsPositive = $this->extractInt($summary, 'ratings_positive');
            $response->ratingsNeutral = $this->extractInt($summary, 'ratings_neutral');
            $response->ratingsNegative = $this->extractInt($summary, 'ratings_negative');
            $response->commentsCount = $this->extractInt($summary, 'comments_count');
        }

        // Extract reviews (if present)
        if (isset($xml->reviews) && isset($xml->reviews->review)) {
            $response->hasReviews = true;

            foreach ($xml->reviews->review as $reviewXml) {
                $review = $this->parseReview($reviewXml);
                if ($review !== null) {
                    $response->reviews[] = $review;
                }
            }
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

        if (empty($reviewId)) {
            return null;
        }

        $review = new ParsedReview();
        $review->reviewId = $reviewId;

        // Check for isVerified attribute
        if (isset($reviewXml['isVerified'])) {
            $review->isVerified = strtolower((string) $reviewXml['isVerified']) === 'true'
                || (string) $reviewXml['isVerified'] === '1';
        }

        $review->reviewUrl = $this->extractString($reviewXml, 'review_url');
        $review->reviewDate = $this->extractDateTime($reviewXml, 'review_date');
        $review->reviewer = $this->extractString($reviewXml, 'reviewer');
        $review->reviewText = $this->extractString($reviewXml, 'text');

        // Rating can be in different formats
        if (isset($reviewXml->review_rating)) {
            $rating = $reviewXml->review_rating;
            $review->reviewRatingStars = $this->extractFloat($rating, 'stars');

            // If stars not present, try to get the value directly
            if ($review->reviewRatingStars === null) {
                $ratingValue = (string) $rating;
                if (is_numeric($ratingValue)) {
                    $review->reviewRatingStars = (float) $ratingValue;
                }
            }
        }

        // Parse review answers
        if (isset($reviewXml->review_answers) && isset($reviewXml->review_answers->answer)) {
            foreach ($reviewXml->review_answers->answer as $answerXml) {
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

        // Get type from attribute
        if (isset($answerXml['type'])) {
            $answer->type = (string) $answerXml['type'];
        } else {
            $answer->type = 'Unknown';
        }

        $answer->date = $this->extractDateTime($answerXml, 'date');
        $answer->text = $this->extractString($answerXml, 'text');

        // If text is directly in the element
        if (empty($answer->text)) {
            $answer->text = trim((string) $answerXml);
        }

        return $answer;
    }

    /**
     * Extract a string value from XML element
     */
    private function extractString(SimpleXMLElement $xml, string $key): ?string
    {
        if (!isset($xml->$key)) {
            return null;
        }

        $value = trim((string) $xml->$key);

        return $value !== '' ? $value : null;
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
