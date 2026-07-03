<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Subscriber;

use Shop4GoogleTagManager\Core\Content\GtmEvent\GtmEventDefinition;
use Shop4GoogleTagManager\Service\GtmEventCatalog;
use Shop4GoogleTagManager\Service\PayloadValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class GtmEventValidationSubscriber implements EventSubscriberInterface
{
    private const TECHNICAL_NAME_PATTERN = '/^[\p{L}\p{N} _\-.]{1,255}$/u';

    public function __construct(
        private readonly PayloadValidator $payloadValidator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();
        $index = 0;

        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== GtmEventDefinition::ENTITY_NAME
                || $command instanceof DeleteCommand
            ) {
                continue;
            }

            $payload = $command->getPayload();

            $this->validateField(
                $payload,
                'ga4_event',
                'ga4Event',
                PayloadValidator::GA4_EVENT_PATTERN,
                'Der GA4-Event-Name muss mit einem Buchstaben beginnen und darf nur Buchstaben, Ziffern und Unterstriche enthalten (max. 40 Zeichen).',
                $index,
                $violations,
            );

            $this->validateField(
                $payload,
                'technical_name',
                'technicalName',
                self::TECHNICAL_NAME_PATTERN,
                'Der technische Name darf nur Buchstaben, Ziffern, Leerzeichen sowie ._- enthalten (1 bis 255 Zeichen).',
                $index,
                $violations,
            );

            $this->validateContext($payload, $index, $violations);
            $this->validatePayload($payload, $index, $violations);

            ++$index;
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    private function validateField(
        array $payload,
        string $storageName,
        string $propertyName,
        string $pattern,
        string $message,
        int $index,
        ConstraintViolationList $violations,
    ): void {
        if (!\array_key_exists($storageName, $payload)) {
            return;
        }

        $value = $payload[$storageName];
        if (!is_string($value) || preg_match($pattern, $value) !== 1) {
            $violations->add($this->violation($message, $value, $propertyName, $index));
        }
    }

    private function validateContext(array $payload, int $index, ConstraintViolationList $violations): void
    {
        if (!\array_key_exists('event_context', $payload)) {
            return;
        }

        $value = $payload['event_context'];
        if (!is_string($value) || !GtmEventCatalog::isValidCustomContext($value)) {
            $violations->add($this->violation(
                'Der Seitenkontext ist ungueltig. Erlaubt: ' . implode(', ', GtmEventCatalog::CUSTOM_CONTEXTS) . '.',
                $value,
                'eventContext',
                $index,
            ));
        }
    }

    private function validatePayload(array $payload, int $index, ConstraintViolationList $violations): void
    {
        if (!\array_key_exists('payload', $payload)) {
            return;
        }

        $value = $payload['payload'];
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $add = fn (string $message): bool => (bool) $violations->add(
            $this->violation($message, $payload['payload'], 'payload', $index),
        );

        if (!is_array($value)) {
            $add('Die Payload muss ein JSON-Objekt sein.');

            return;
        }

        if (count($value) > PayloadValidator::MAX_KEYS) {
            $add('Die Payload darf maximal ' . PayloadValidator::MAX_KEYS . ' Felder enthalten.');

            return;
        }

        foreach (array_keys($value) as $key) {
            if ($this->payloadValidator->isReservedKey($key)) {
                $add('Die Payload darf die reservierten Felder "event"/"ecommerce" nicht setzen.');

                return;
            }
            if (!$this->payloadValidator->isValidPayloadKey($key)) {
                $add('Payload-Feldnamen muessen mit einem Buchstaben beginnen und duerfen nur Buchstaben, Ziffern und Unterstriche enthalten (max. 40 Zeichen).');

                return;
            }
        }

        if (!$this->payloadValidator->isWithinLimits($value, PayloadValidator::MAX_DEPTH)) {
            $add(
                'Die Payload ist ungueltig: max. ' . PayloadValidator::MAX_DEPTH . ' Verschachtelungsebenen und '
                . 'max. ' . PayloadValidator::MAX_VALUE_LENGTH . ' Zeichen je Wert.',
            );
        }
    }

    private function violation(string $message, mixed $invalidValue, string $propertyPath, int $index): ConstraintViolation
    {
        return new ConstraintViolation(
            $message,
            $message,
            ['value' => $invalidValue],
            $invalidValue,
            "/{$index}/{$propertyPath}",
            $invalidValue,
        );
    }
}
