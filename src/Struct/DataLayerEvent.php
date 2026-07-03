<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Struct;

use Shopware\Core\Framework\Struct\Struct;

class DataLayerEvent extends Struct
{
    public function __construct(
        public readonly string $event,
        public readonly array $data = [],
    ) {
    }


    public function jsonSerialize(): array
    {
        $data = $this->data;
        unset($data['event']);

        return array_merge(['event' => $this->event], $data);
    }
}
