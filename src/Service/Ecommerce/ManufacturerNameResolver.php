<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service\Ecommerce;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ManufacturerNameResolver
{
    /** @var array<string, array<string, string|null>> */
    private array $cache = [];

    public function __construct(
        private readonly EntityRepository $manufacturerRepository,
    ) {
    }

    public function resolve(array $manufacturerIds, Context $context): array
    {
        $ids = array_values(array_unique(array_filter(
            $manufacturerIds,
            static fn (?string $id): bool => is_string($id) && $id !== '',
        )));

        if ($ids === []) {
            return [];
        }

        $cacheKey = $context->getLanguageId();
        $cached = &$this->cache[$cacheKey];
        $cached ??= [];

        $missing = array_values(array_filter($ids, static fn (string $id): bool => !\array_key_exists($id, $cached)));
        if ($missing !== []) {
            foreach ($missing as $id) {
                $cached[$id] = null;
            }
            foreach ($this->manufacturerRepository->search(new Criteria($missing), $context) as $manufacturer) {
                $name = $manufacturer->getTranslation('name') ?? $manufacturer->get('name');
                if (is_string($name) && $name !== '') {
                    $cached[$manufacturer->getId()] = $name;
                }
            }
        }

        $map = [];
        foreach ($ids as $id) {
            if (is_string($cached[$id] ?? null)) {
                $map[$id] = $cached[$id];
            }
        }

        return $map;
    }
}
