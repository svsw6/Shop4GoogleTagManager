<?php declare(strict_types=1);

namespace Shop4GoogleTagManager;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class Shop4GoogleTagManager extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        $this->removeConfiguration($connection);
        $this->removeTables($connection);
    }

    private function removeConfiguration(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` LIKE :prefix',
            ['prefix' => 'Shop4GoogleTagManager.config.%'],
        );
    }

    private function removeTables(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `s4gtm_event_sales_channel`');
        $connection->executeStatement('DROP TABLE IF EXISTS `s4gtm_event`');
    }
}
