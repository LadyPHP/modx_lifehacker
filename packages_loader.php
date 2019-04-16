<?php
/**
 * Скрипт для установки часто используемых пакетов и системных настроек для новых проектов на MODX
 * Запускается после основной установки (следующий pupline)
 */

// массив пакетов для установки
$listPackagesToInstall = array(
    1 => [ // пакеты от стандартного провайдера
        'sdStore',
        'Ace',
        'AdminTools',
        'Collections',
        'MIGX',
        'pdoTools',
        'pThumb',
        'VersionX',
        'translit',
        'TinyMCE',
        'FormIt',
    ],
    2 => [ // прочие пакеты
         'Tickets',
    ]
);

define('MODX_API_MODE', true);

$modx_cache_disabled = false; // отключаем кэш на время выполения скрипта
header("Content-type: text/plain");

// подключаем конфигурационный файл, откуда получим все основные данные
include(dirname(__FILE__) . '/config.core.php');
if (!defined('MODX_CORE_PATH')) define('MODX_CORE_PATH', dirname(__FILE__) . '/core/');

// подключаем класс Modx из ядра
if (!@include_once(MODX_CORE_PATH . "model/modx/modx.class.php")) {
    $errorMessage = 'Site temporarily unavailable';
    @include(MODX_CORE_PATH . 'error/unavailable.include.php');
    header('HTTP/1.1 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error 503</h1><p>{$errorMessage}</p></body></html>";
    exit();
}

// новый инстанс, который будем использовать для кастомизации
$modx = new modX();
if (!is_object($modx) || !($modx instanceof modX)) {
    @ob_end_flush();
    $errorMessage = '<a href="setup/">MODX not installed. Install now?</a>';
    @include(MODX_CORE_PATH . 'error/unavailable.include.php');
    header('HTTP/1.1 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error 503</h1><p>{$errorMessage}</p></body></html>";
    exit();
}

$modx->initialize('mgr');
$modx->addPackage('modx.transport', dirname(__FILE__) . '/core/model/');
foreach ($listPackagesToInstall as $providerId => $installPackages) {
    // получаем провайдеров
    $provider = $modx->getObject('transport.modTransportProvider', $providerId);
    if (empty($provider)) {
        echo 'Could not find provider' . "\n";
        return;
    }
    $provider->getClient();
    $modx->getVersionData();
    $productVersion = $modx->version['code_name'] . '-' . $modx->version['full_version'];

    foreach ($installPackages as $packageName) {
        $response = $provider->request('package', 'GET', array(
            'query' => $packageName
        ));

        if (!empty($response)) {
            $foundPackages = simplexml_load_string($response->response);
            if ($total = $foundPackages['total'] > 0) {
                // Обрабатываем список пакетов в цикле
                foreach ($foundPackages as $foundPackage) {
                    if ($foundPackage->name == $packageName) {
                        $sig = explode('-', $foundPackage->signature);
                        $versionSignature = explode('.', $sig[1]);

                        // загружаем файлы пакетов
                        file_put_contents(
                            $modx->getOption('core_path') . 'packages/' . $foundPackage->signature . '.transport.zip', file_get_contents($foundPackage->location));

                        /**
                         * Добавляем в пакет как объект с нужными параметрами
                         * @var modTransportPackage $package
                         */
                        $package = $modx->newObject('transport.modTransportPackage');
                        $package->set('signature', $foundPackage->signature);
                        $package->fromArray(array(
                            'created'       =>  date('Y-m-d h:i:s'),
                            'updated'       =>  null,
                            'state'         =>  1,
                            'workspace'     =>  1,
                            'provider'      => $providerId,
                            'source'        => $foundPackage->signature . '.transport.zip',
                            'package_name'  => $sig[0],
                            'version_major' => $versionSignature[0],
                            'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
                            'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
                        ));

                        if (!empty($sig[2])) {
                            $r = preg_split('/([0-9]+)/', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);

                            if (is_array($r) && !empty($r)) {
                                $package->set('release', $r[0]);
                                $package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
                            } else {
                                $package->set('release', $sig[2]);
                            }
                        }
                        $success = $package->save();

                        if ($success) {
                            $package->install(); // инициализируем установку
                            echo 'Package ' . $foundPackage->name . ' installed.' . "\n";
                        } else {
                            echo 'Could not save package ' . $foundPackage->name . "\n";
                        }

                        break;
                    }
                }
            } else echo 'Package ' . $packageName . ' not found' . "\n";
        }
    }
}
