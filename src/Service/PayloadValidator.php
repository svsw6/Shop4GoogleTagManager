<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

class PayloadValidator
{
    public const GA4_EVENT_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{0,39}$/';
    public const PAYLOAD_KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{0,39}$/';
    public const MAX_KEYS = 30;
    public const MAX_DEPTH = 3;
    public const MAX_VALUE_LENGTH = 500;

    public const RESERVED_KEYS = ['event', 'ecommerce'];

    public function isValidGa4EventName(string $name): bool
    {
        return preg_match(self::GA4_EVENT_PATTERN, $name) === 1;
    }

    public function isValidPayloadKey(int|string $key): bool
    {
        return is_string($key) && preg_match(self::PAYLOAD_KEY_PATTERN, $key) === 1;
    }

    public function isReservedKey(int|string $key): bool
    {
        return in_array((string) $key, self::RESERVED_KEYS, true);
    }

    public function isWithinLimits(mixed $value, int $remaining): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) <= self::MAX_VALUE_LENGTH;
        }
        if (!is_array($value)) {
            return is_scalar($value) || $value === null;
        }
        if ($remaining <= 0) {
            return false;
        }
        foreach ($value as $child) {
            if (!$this->isWithinLimits($child, $remaining - 1)) {
                return false;
            }
        }

        return true;
    }

    public function sanitizePayload(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $key => $item) {
            if (count($clean) >= self::MAX_KEYS) {
                break;
            }
            if (!$this->isValidPayloadKey($key) || $this->isReservedKey($key)) {
                continue;
            }
            if (!$this->isWithinLimits($item, self::MAX_DEPTH - 1)) {
                continue;
            }
            $clean[$key] = $item;
        }

        return $clean;
    }
}
