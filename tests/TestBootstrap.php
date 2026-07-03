<?php declare(strict_types=1);

use Composer\Autoload\ClassLoader;

$pluginRoot = dirname(__DIR__);

// autoloader sowohl fuer den standalone-betrieb (eigenes vendor) als auch fuer den betrieb
// innerhalb einer shopware-installation (custom/plugins/...) finden.
$autoloaders = [
    $pluginRoot . '/vendor/autoload.php',
    dirname(__DIR__, 4) . '/vendor/autoload.php',
];

$loader = null;
foreach ($autoloaders as $file) {
    if (is_file($file)) {
        $loader = require $file;
        break;
    }
}

if (!$loader instanceof ClassLoader) {
    throw new RuntimeException(
        'Composer-Autoloader nicht gefunden. Bitte "composer install" ausfuehren oder die '
        . 'tests aus dem shopware-root starten.'
    );
}

// die plugin-namespaces werden im normalbetrieb erst vom shopware-kernel registriert; fuer die
// reinen unit-tests registrieren wir sie hier direkt, damit kein kernel gebootet werden muss.
$loader->addPsr4('Shop4GoogleTagManager\\', $pluginRoot . '/src/');
$loader->addPsr4('Shop4GoogleTagManager\\Tests\\', $pluginRoot . '/tests/');
