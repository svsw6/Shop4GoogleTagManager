<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\PayloadValidator;

class PayloadValidatorTest extends TestCase
{
    private PayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PayloadValidator();
    }

    #[DataProvider('ga4NameProvider')]
    public function testIsValidGa4EventName(string $name, bool $expected): void
    {
        static::assertSame($expected, $this->validator->isValidGa4EventName($name));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function ga4NameProvider(): array
    {
        return [
            'gueltig' => ['view_item', true],
            'gueltig mit ziffern' => ['view_item_2', true],
            'leer' => ['', false],
            'beginnt mit ziffer' => ['2view', false],
            'leerzeichen' => ['view item', false],
            'sonderzeichen' => ['view-item', false],
            'zu lang' => [str_repeat('a', 41), false],
        ];
    }

    public function testIsReservedKey(): void
    {
        static::assertTrue($this->validator->isReservedKey('event'));
        static::assertTrue($this->validator->isReservedKey('ecommerce'));
        static::assertFalse($this->validator->isReservedKey('value'));
    }

    public function testIsValidPayloadKey(): void
    {
        static::assertTrue($this->validator->isValidPayloadKey('affiliation'));
        static::assertFalse($this->validator->isValidPayloadKey('bad key'));
        static::assertFalse($this->validator->isValidPayloadKey(0));
    }

    public function testSanitizePayloadDropsInvalidEntries(): void
    {
        $result = $this->validator->sanitizePayload([
            'ok' => 'value',
            'event' => 'reserved',
            'ecommerce' => ['x' => 1],
            'bad key!' => 'x',
            'tooDeep' => ['a' => ['b' => ['c' => 1]]],
            'okNested' => ['a' => ['b' => 1]],
        ]);

        static::assertSame(['ok' => 'value', 'okNested' => ['a' => ['b' => 1]]], $result);
    }

    public function testSanitizePayloadDropsOverlongStringValues(): void
    {
        $maxValue = str_repeat('a', PayloadValidator::MAX_VALUE_LENGTH);
        $tooLong = str_repeat('a', PayloadValidator::MAX_VALUE_LENGTH + 1);

        $result = $this->validator->sanitizePayload([
            'ok' => $maxValue,
            'tooLong' => $tooLong,
            'nestedTooLong' => ['inner' => $tooLong],
        ]);

        static::assertSame(['ok' => $maxValue], $result);
    }

    public function testIsWithinLimitsRejectsOverlongString(): void
    {
        static::assertTrue($this->validator->isWithinLimits(str_repeat('a', PayloadValidator::MAX_VALUE_LENGTH), 1));
        static::assertFalse($this->validator->isWithinLimits(str_repeat('a', PayloadValidator::MAX_VALUE_LENGTH + 1), 1));
        // nicht-string-skalare bleiben unberuehrt
        static::assertTrue($this->validator->isWithinLimits(42, 1));
        static::assertTrue($this->validator->isWithinLimits(true, 1));
        static::assertTrue($this->validator->isWithinLimits(null, 1));
    }

    public function testSanitizePayloadCapsKeyCount(): void
    {
        $input = [];
        for ($i = 0; $i < 50; ++$i) {
            $input['k' . $i] = $i;
        }

        static::assertCount(PayloadValidator::MAX_KEYS, $this->validator->sanitizePayload($input));
    }

    public function testSanitizePayloadAcceptsJsonString(): void
    {
        static::assertSame(['a' => 1], $this->validator->sanitizePayload('{"a":1}'));
    }

    public function testSanitizePayloadReturnsEmptyForNonArray(): void
    {
        static::assertSame([], $this->validator->sanitizePayload('not-json'));
        static::assertSame([], $this->validator->sanitizePayload(null));
        static::assertSame([], $this->validator->sanitizePayload(42));
    }
}
