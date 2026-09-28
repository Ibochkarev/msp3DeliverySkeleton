<?php

use MODX\Revolution\modCategory;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use xPDO\Transport\xPDOFileVehicle;
use xPDO\Transport\xPDOScriptVehicle;
use xPDO\Transport\xPDOTransport;

class Msp3DeliverySkeletonPackage
{
    private modX $modx;
    private array $config = [];
    private modCategory $category;
    private array $category_attributes = [];
    private modPackageBuilder $builder;

    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;
        $this->modx->initialize('mgr');

        $root = dirname(__FILE__, 2) . '/';
        $core = $root . 'core/components/' . $config['name_lower'] . '/';
        $assets = $root . 'assets/components/' . $config['name_lower'] . '/';

        $this->config = array_merge([
            'log_level' => modX::LOG_LEVEL_INFO,
            'log_target' => (php_sapi_name() === 'cli' ? 'ECHO' : 'HTML'),
            'root' => $root,
            'build' => $root . '_build/',
            'elements' => $root . '_build/elements/',
            'resolvers' => $root . '_build/resolvers/',
            'core' => $core,
            'assets' => $assets,
        ], $config);
        $this->modx->setLogLevel($this->config['log_level']);
        $this->modx->setLogTarget($this->config['log_target']);

        $this->initialize();
    }

    public function process(): modPackageBuilder
    {
        $this->encryptionSetup();

        $elements = scandir($this->config['elements']);
        foreach ($elements as $element) {
            if (in_array($element[0], ['_', '.'], true)) {
                continue;
            }
            $name = preg_replace('#\.php$#', '', $element);
            if (is_string($name) && method_exists($this, $name)) {
                $this->{$name}();
            }
        }

        $vehicle = $this->builder->createVehicle($this->category, $this->category_attributes);

        $vehicle->resolve('file', [
            'source' => $this->config['core'],
            'target' => "return MODX_CORE_PATH . 'components/';",
        ]);
        if (is_dir($this->config['assets'])) {
            $vehicle->resolve('file', [
                'source' => $this->config['assets'],
                'target' => "return MODX_ASSETS_PATH . 'components/';",
            ]);
        }

        $resolvers = array_filter(
            scandir($this->config['resolvers']),
            fn ($r) => !in_array($r[0], ['_', '.'], true) && substr($r, -4) === '.php'
        );
        sort($resolvers);
        foreach ($resolvers as $resolver) {
            if ($resolver === 'resolve.encryption.php') {
                continue;
            }
            if ($vehicle->resolve('php', ['source' => $this->config['resolvers'] . $resolver])) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Added resolver ' . preg_replace('#\.php$#', '', $resolver));
            }
        }

        $this->builder->putVehicle($vehicle);

        if (!empty($this->config['encrypt'])) {
            $this->builder->putVehicle($this->builder->createVehicle(
                ['source' => $this->config['resolvers'] . 'resolve.encryption.php'],
                ['vehicle_class' => xPDOScriptVehicle::class]
            ));
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver (for uninstall)');
        }
        $this->builder->setPackageAttributes([
            'changelog' => $this->readDocFile('changelog.txt'),
            'license' => $this->readDocFile('license.txt'),
            'readme' => $this->readDocFile('readme.txt'),
            'requires' => [
                'php' => '>=8.2.0',
                'modx' => '>=3.0.3',
                'minishop3' => '>=1.14.0-beta1',
            ],
        ]);

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
        $this->builder->pack();

        return $this->builder;
    }

    private function initialize(): void
    {
        $this->builder = new modPackageBuilder($this->modx);
        $this->builder->createPackage(
            $this->config['name_lower'],
            $this->config['version'],
            $this->config['release']
        );
        $this->builder->registerNamespace(
            $this->config['name_lower'],
            false,
            true,
            '{core_path}components/' . $this->config['name_lower'] . '/'
        );

        $this->category = $this->modx->newObject(modCategory::class);
        $this->category->set('category', $this->config['name']);
        $this->category_attributes = [
            xPDOTransport::UNIQUE_KEY => 'category',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'Plugins' => [
                    xPDOTransport::UNIQUE_KEY => 'name',
                    xPDOTransport::PRESERVE_KEYS => false,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::RELATED_OBJECTS => true,
                    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                        'PluginEvents' => [
                            xPDOTransport::UNIQUE_KEY => ['pluginid', 'event'],
                            xPDOTransport::PRESERVE_KEYS => true,
                            xPDOTransport::UPDATE_OBJECT => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * EncryptedVehicle + resolve.encryption.php, same path as msp3PaymentSkeleton.
     * encrypt=true talks to modstore.pro. Local builds: ENCRYPT=0 php _build/build.php
     */
    private function encryptionSetup(): void
    {
        if (empty($this->config['encrypt'])) {
            $this->modx->log(modX::LOG_LEVEL_INFO, '[msp3DeliverySkeleton] Encryption disabled (local build).');

            return;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[msp3DeliverySkeleton] Encryption enabled.');

        require_once $this->config['core'] . 'src/Transport/EncryptedVehicle.php';

        $this->builder->package->put(
            [
                'source' => $this->config['core'] . 'src/Transport/EncryptedVehicle.php',
                'target' => "return MODX_CORE_PATH . 'components/" . $this->config['name_lower'] . "/Transport/';",
            ],
            [
                'vehicle_class' => xPDOFileVehicle::class,
                xPDOTransport::UNINSTALL_FILES => false,
            ]
        );
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added EncryptedVehicle class to package');

        $this->builder->package->put(
            [
                'source' => $this->config['core'] . 'src/Transport/encryptedvehicle.class.php',
                'target' => "return MODX_CORE_PATH . 'model/" . $this->config['name_lower'] . "/transport/';",
            ],
            [
                'vehicle_class' => xPDOFileVehicle::class,
                xPDOTransport::UNINSTALL_FILES => false,
            ]
        );
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added EncryptedVehicle compatibility class to core/model');

        $this->builder->putVehicle($this->builder->createVehicle(
            ['source' => $this->config['resolvers'] . 'resolve.encryption.php'],
            ['vehicle_class' => xPDOScriptVehicle::class]
        ));
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver');

        $this->category_attributes['vehicle_class'] = 'Ibochkarev\\Msp3DeliverySkeleton\\Transport\\EncryptedVehicle';
        $this->category_attributes[xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL] = true;
    }

    private function settings(): void
    {
        $settings = include $this->config['elements'] . 'settings.php';
        if (!is_array($settings)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package System Settings');

            return;
        }
        $attributes = [
            xPDOTransport::UNIQUE_KEY => 'key',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => false,
            xPDOTransport::RELATED_OBJECTS => false,
        ];
        foreach ($settings as $name => $data) {
            $setting = $this->modx->newObject(modSystemSetting::class);
            $setting->fromArray(array_merge([
                'key' => $this->config['name_lower'] . '_' . $name,
                'namespace' => $this->config['name_lower'],
            ], $data), '', true, true);
            $vehicle = $this->builder->createVehicle($setting, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged ' . count($settings) . ' System Settings');
    }

    private function plugins(): void
    {
        $plugins = include $this->config['elements'] . 'plugins.php';
        if (!is_array($plugins) || $plugins === []) {
            return;
        }
        $source = $this->config['core'] . 'elements/plugins/';
        foreach ($plugins as $name => $data) {
            $plugin = $this->modx->newObject(modPlugin::class);
            $code = file_exists($source . $data['file'] . '.php')
                ? trim((string) file_get_contents($source . $data['file'] . '.php'))
                : '';
            if (preg_match('#<\?php(.*)#is', $code, $m)) {
                $code = trim(rtrim(trim($m[1] ?? ''), '?>'));
            }
            $plugin->fromArray([
                'name' => $name,
                'description' => $data['description'] ?? '',
                'plugincode' => $code,
                'category' => 0,
                'static' => false,
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/plugins/' . $data['file'] . '.php',
            ], '', true, true);
            $events = [];
            foreach ($data['events'] ?? [] as $eventName => $eventData) {
                $event = $this->modx->newObject(modPluginEvent::class);
                $event->fromArray([
                    'event' => $eventName,
                    'priority' => is_array($eventData) ? ($eventData['priority'] ?? 0) : 0,
                ], '', true, true);
                $events[] = $event;
            }
            $plugin->addMany($events);
            $this->category->addMany($plugin);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged ' . count($plugins) . ' Plugin(s)');
    }

    private function readDocFile(string $filename): string
    {
        $filepath = $this->config['core'] . 'docs/' . $filename;
        if (!file_exists($filepath) && $filename === 'readme.txt') {
            $filepath = $this->config['root'] . 'README.md';
        }
        if (!file_exists($filepath)) {
            return '';
        }
        $content = file_get_contents($filepath);

        return $content !== false ? $content : '';
    }
}

if (php_sapi_name() === 'cli' && !isset($_SESSION)) {
    $_SESSION = [];
}

if (!file_exists(dirname(__FILE__) . '/config.inc.php')) {
    exit('Could not load config. Place the component in Extras/ of your MODX site or set MODX_CORE_PATH.');
}

$config = require dirname(__FILE__) . '/config.inc.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
$modx = new modX();
$install = new Msp3DeliverySkeletonPackage($modx, $config);
$builder = $install->process();

if (!empty($config['download'])) {
    $name = $builder->getSignature() . '.transport.zip';
    $path = $modx->getOption('core_path', null, MODX_CORE_PATH) . 'packages/';
    if ($path && file_exists($path . $name)) {
        $content = file_get_contents($path . $name);
        if ($content !== false) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $name);
            header('Content-Length: ' . strlen($content));
            exit($content);
        }
    }
}
