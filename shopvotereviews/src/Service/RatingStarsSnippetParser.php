<?php
/**
 * Parses ShopVote RatingStars/floating-badge code into safe, canonical settings.
 */

declare(strict_types=1);

namespace ShopVote\ShopVoteReviews\Service;

class RatingStarsSnippetParser
{
    private const MAX_SNIPPET_LENGTH = 32768;

    /**
     * @return array{
     *     script_url:string,
     *     initializer:string,
     *     shop_id:int,
     *     badge_type:int,
     *     language:?string,
     *     z_index:?int,
     *     function:string,
     *     arguments:list<int|string>
     * }
     */
    public function parse(string $snippet): array
    {
        if (trim($snippet) === '') {
            throw new \InvalidArgumentException('The RatingStars JavaScript code is required.');
        }
        if (strlen($snippet) > self::MAX_SNIPPET_LENGTH) {
            throw new \InvalidArgumentException('The RatingStars JavaScript code is too large.');
        }

        if (!preg_match_all('/<script\b([^>]*)>(.*?)<\/script\s*>/is', $snippet, $scriptTags, PREG_SET_ORDER)) {
            throw new \InvalidArgumentException('No ShopVote script was found.');
        }

        $outsideScripts = preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', '', $snippet);
        $outsideScripts = preg_replace('/<!--.*?-->/s', '', (string) $outsideScripts);
        if (trim((string) $outsideScripts) !== '') {
            throw new \InvalidArgumentException('RatingStars code may contain only ShopVote script tags.');
        }

        $scriptUrl = null;
        $inlineCode = [];

        foreach ($scriptTags as $scriptTag) {
            $attributes = $scriptTag[1];
            $body = $scriptTag[2];
            $src = $this->getScriptSource($attributes);

            if ($src !== null) {
                if ($scriptUrl !== null || trim($body) !== '') {
                    throw new \InvalidArgumentException('Use exactly one external ShopVote script tag.');
                }
                $scriptUrl = $this->validateScriptUrl($src);
                continue;
            }

            $this->assertOnlySupportedAttributes($attributes, false);
            if (trim($body) !== '') {
                $inlineCode[] = $body;
            }
        }

        if ($scriptUrl === null) {
            throw new \InvalidArgumentException('The script URL must use HTTPS and widgets.shopvote.de.');
        }
        if ($inlineCode === []) {
            throw new \InvalidArgumentException('No RatingStars initialization code was found.');
        }

        return array_merge(
            ['script_url' => $scriptUrl],
            $this->parseInitializer(implode("\n", $inlineCode))
        );
    }

    private function getScriptSource(string $attributes): ?string
    {
        if (!preg_match_all('/\bsrc\s*=\s*(["\'])(.*?)\1/is', $attributes, $matches)) {
            return null;
        }
        if (count($matches[2]) !== 1) {
            throw new \InvalidArgumentException('Use exactly one src attribute per script tag.');
        }

        $this->assertOnlySupportedAttributes($attributes, true);

        return html_entity_decode(trim($matches[2][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertOnlySupportedAttributes(string $attributes, bool $allowSrc): void
    {
        $remaining = $attributes;
        if ($allowSrc) {
            $remaining = preg_replace('/\bsrc\s*=\s*(["\']).*?\1/is', '', $remaining, 1);
            $remaining = preg_replace('/(?:^|\s)defer(?=\s|$)/i', ' ', (string) $remaining, 1);
        }
        $remaining = preg_replace('/\btype\s*=\s*(["\'])(?:text|application)\/javascript\1/is', '', (string) $remaining);

        if (trim((string) $remaining) !== '') {
            throw new \InvalidArgumentException('The RatingStars script tag contains unsupported attributes.');
        }
    }

    private function validateScriptUrl(string $url): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('The script URL must use HTTPS and widgets.shopvote.de.');
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        if (($parts['scheme'] ?? '') !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'widgets.shopvote.de'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || !str_starts_with($path, '/js/')
            || !str_ends_with(strtolower($path), '.js')) {
            throw new \InvalidArgumentException('The script URL must use HTTPS and widgets.shopvote.de.');
        }

        return $url;
    }

    /**
     * @return array{
     *     initializer:string,
     *     shop_id:int,
     *     badge_type:int,
     *     language:?string,
     *     z_index:?int,
     *     function:string,
     *     arguments:list<int|string>
     * }
     */
    private function parseInitializer(string $code): array
    {
        $code = preg_replace('/\/\*.*?\*\//s', '', $code);
        $code = preg_replace('/(^|\R)\s*\/\/[^\r\n]*/', '$1', (string) $code);
        $code = str_replace(['<!--', '-->', '<![CDATA[', ']]>'], '', (string) $code);
        $code = $this->unwrapOfficialLoadHandler((string) $code);

        preg_match_all(
            '/\b(?:var|let|const)\s+(myShopID|myBadgetType|myLanguage|myZIndex|mySrc)\s*=\s*([^;]+)\s*;/s',
            $code,
            $assignments,
            PREG_SET_ORDER
        );

        $values = [];
        foreach ($assignments as $assignment) {
            $name = $assignment[1];
            if (array_key_exists($name, $values)) {
                throw new \InvalidArgumentException('RatingStars variables may be assigned only once.');
            }
            $values[$name] = trim($assignment[2]);
        }

        preg_match_all(
            '/\b(createRBadge|createBadget|createVBadge)\s*\(([^;]*)\)\s*;/s',
            $code,
            $calls,
            PREG_SET_ORDER
        );
        if (count($calls) !== 1) {
            throw new \InvalidArgumentException('Use exactly one supported ShopVote badge initializer.');
        }

        $remaining = preg_replace(
            '/\b(?:var|let|const)\s+(?:myShopID|myBadgetType|myLanguage|myZIndex|mySrc)\s*=\s*[^;]+\s*;/s',
            '',
            $code
        );
        $remaining = preg_replace(
            '/\b(?:createRBadge|createBadget|createVBadge)\s*\([^;]*\)\s*;/s',
            '',
            (string) $remaining
        );
        if (trim((string) $remaining, " \t\n\r\0\x0B;") !== '') {
            throw new \InvalidArgumentException('The RatingStars code contains unsupported JavaScript.');
        }

        foreach (['myShopID', 'myBadgetType', 'mySrc'] as $required) {
            if (!array_key_exists($required, $values)) {
                throw new \InvalidArgumentException('The RatingStars code is missing ' . $required . '.');
            }
        }

        $shopId = $this->parseInteger($values['myShopID'], 1, 2147483647, 'Shop ID');
        $badgeType = $this->parseInteger($values['myBadgetType'], 1, 99, 'badge type');
        $this->validateProtocolValue($values['mySrc']);

        $language = null;
        if (isset($values['myLanguage'])) {
            $language = strtoupper($this->parseString($values['myLanguage']));
            if (!in_array($language, ['DE', 'EN', 'FR', 'IT', 'NL', 'ES'], true)) {
                throw new \InvalidArgumentException('The RatingStars language is not supported.');
            }
        }

        $zIndex = null;
        if (isset($values['myZIndex'])) {
            $zIndex = $this->parseInteger($values['myZIndex'], 0, 2147483647, 'z-index');
        }

        $function = $calls[0][1];
        $arguments = $this->parseCallArguments($function, $calls[0][2]);

        $lines = [
            'var myShopID = ' . $shopId . ';',
            'var myBadgetType = ' . $badgeType . ';',
        ];
        if ($language !== null) {
            $lines[] = 'var myLanguage = ' . $this->encodeString($language) . ';';
        }
        if ($zIndex !== null) {
            $lines[] = 'var myZIndex = ' . $zIndex . ';';
        }
        $lines[] = 'var mySrc = "https";';
        $canonicalArguments = array_map(
            fn (int|string $argument): string => is_int($argument)
                ? (string) $argument
                : $this->encodeString($argument),
            $arguments
        );
        $lines[] = $function . '(myShopID, myBadgetType, mySrc'
            . ($canonicalArguments === [] ? '' : ', ' . implode(', ', $canonicalArguments))
            . ');';

        return [
            'initializer' => implode("\n", $lines),
            'shop_id' => $shopId,
            'badge_type' => $badgeType,
            'language' => $language,
            'z_index' => $zIndex,
            'function' => $function,
            'arguments' => $arguments,
        ];
    }

    private function unwrapOfficialLoadHandler(string $code): string
    {
        if (!preg_match('/\b(?:loadBadge|window\.addEventListener|window\.attachEvent)\b/', $code)) {
            return $code;
        }

        $eventListener = 'window\.addEventListener\s*\?\s*'
            . 'window\.addEventListener\(\s*(["\'])load\1\s*,\s*loadBadge\s*,\s*!1\s*\)';
        $attachEvent = 'window\.attachEvent\s*&&\s*'
            . 'window\.attachEvent\(\s*(["\'])onload\2\s*,\s*loadBadge\s*\)';

        if (!preg_match(
            '/^\s*' . $eventListener . '\s*:\s*' . $attachEvent . '\s*;\s*'
            . 'function\s+loadBadge\s*\(\s*\)\s*\{(?<body>.*)\}\s*;?\s*$/s',
            $code,
            $match
        )) {
            throw new \InvalidArgumentException('The RatingStars code contains unsupported JavaScript.');
        }

        return $match['body'];
    }

    /**
     * @return list<int|string>
     */
    private function parseCallArguments(string $function, string $argumentList): array
    {
        $arguments = preg_split('/\s*,\s*/', trim($argumentList));
        if ($arguments === false || count($arguments) < 3
            || $arguments[0] !== 'myShopID'
            || $arguments[1] !== 'myBadgetType'
            || $arguments[2] !== 'mySrc') {
            throw new \InvalidArgumentException('The ShopVote badge initializer has unsupported arguments.');
        }

        $optional = array_slice($arguments, 3);
        if ($function !== 'createBadget' && $optional !== []) {
            throw new \InvalidArgumentException('The ShopVote badge initializer has unsupported arguments.');
        }
        if (count($optional) > 5) {
            throw new \InvalidArgumentException('The ShopVote badge initializer has unsupported arguments.');
        }

        $canonical = [];
        foreach ($optional as $index => $argument) {
            if (in_array($index, [0, 1, 4], true)) {
                $canonical[] = $this->parseInteger($argument, 0, 10000, 'badge position');
                continue;
            }

            $value = strtolower($this->parseString($argument));
            $allowed = $index === 2 ? ['', 'left', 'right'] : ['', 'top', 'bottom'];
            if (!in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException('The ShopVote badge alignment is not supported.');
            }
            $canonical[] = $value;
        }

        return $canonical;
    }

    private function parseInteger(string $value, int $minimum, int $maximum, string $label): int
    {
        if (!preg_match('/^(?:(["\'])(\d{1,10})\1|(\d{1,10}))$/', trim($value), $match)) {
            throw new \InvalidArgumentException('The RatingStars ' . $label . ' is invalid.');
        }

        $integer = (int) (($match[2] ?? '') !== '' ? $match[2] : $match[3]);
        if ($integer < $minimum || $integer > $maximum) {
            throw new \InvalidArgumentException('The RatingStars ' . $label . ' is invalid.');
        }

        return $integer;
    }

    private function parseString(string $value): string
    {
        if (!preg_match('/^(["\'])([^"\']*)\1$/', trim($value), $match)) {
            throw new \InvalidArgumentException('The RatingStars code contains an unsupported value.');
        }

        return $match[2];
    }

    private function validateProtocolValue(string $value): void
    {
        $value = trim($value);
        if (preg_match('/^(["\'])https\1$/', $value)) {
            return;
        }

        $quotedHttpsFirst = '["\']https:["\']\s*={2,3}\s*document\.location\.protocol';
        $protocolFirst = 'document\.location\.protocol\s*={2,3}\s*["\']https:["\']';
        if (!preg_match(
            '/^\(?\s*(?:' . $quotedHttpsFirst . '|' . $protocolFirst . ')\s*\?\s*'
            . '["\']https["\']\s*:\s*["\']http["\']\s*\)?$/',
            $value
        )) {
            throw new \InvalidArgumentException('The RatingStars protocol selector is invalid.');
        }
    }

    private function encodeString(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
