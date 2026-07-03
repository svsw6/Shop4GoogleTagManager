<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1750118400CreateGtmEventTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1750118400;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `s4gtm_event` (
                `id`                BINARY(16)   NOT NULL,
                `technical_name`    VARCHAR(255) NOT NULL,
                `event_context`     VARCHAR(32)  NOT NULL,
                `ga4_event`         VARCHAR(255) NOT NULL,
                `payload`           JSON         NULL,
                `active`            TINYINT(1)   NOT NULL DEFAULT 1,
                `priority`          INT          NOT NULL DEFAULT 0,
                `created_at`        DATETIME(3)  NOT NULL,
                `updated_at`        DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                KEY `idx.s4gtm_event.context` (`event_context`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `s4gtm_event_sales_channel` (
                `s4gtm_event_id`    BINARY(16) NOT NULL,
                `sales_channel_id`  BINARY(16) NOT NULL,
                PRIMARY KEY (`s4gtm_event_id`, `sales_channel_id`),
                KEY `idx.s4gtm_event_sales_channel.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.s4gtm_event_sales_channel.event_id` FOREIGN KEY (`s4gtm_event_id`)
                    REFERENCES `s4gtm_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.s4gtm_event_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
