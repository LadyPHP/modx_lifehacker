<?php
/**
 * Скрипт для автоматической установки системных настроек, которые не меняются из проекта в проет MODX
 */


header("Content-type: text/plain");

// подключаем конфигурационный файл, откуда получим все основные данные
require_once dirname(__FILE__) . '/config.core.php';

// подключаем класс Modx из ядра
if (!@include_once(MODX_CORE_PATH . "model/modx/modx.class.php")) {
    $errorMessage = 'Site temporarily unavailable';
    @include(MODX_CORE_PATH . 'error/unavailable.include.php');
    header('HTTP/1.1 503 Service Unavailable');
    echo "<html><title>Error 503: Site temporarily unavailable</title><body><h1>Error 503</h1><p>{$errorMessage}</p></body></html>";
    exit();
}

$url = $modx->config['site_url'];
$url = preg_match('#http://(.+?)/#is', $url, $host);

// Системные настройки
$settings = [
    'publish_default'               => 1,
    'tvs_below_content'             => 1,
    'upload_maxsize'                => '10485760',
    // lang
    'cultureKey'                    => 'ru',
    'fe_editor_lang'                => 'ru',
    'manager_lang_attribute'        => 'ru',
    'manager_language'              => 'ru',
    // RL
    'automatic_alias'               => 1,
    'friendly_urls'                 => 1,
    'friendly_alias_translit'       => 'russian',
    'friendly_alias_restrict_chars' => 'alphanumeric',
];

// Категории элементов в админке
$categories = [
    ['category' => 'Главная', 'parent' => 0],
    ['category' => 'Сквозные элементы', 'parent' => 0],
];

// Ресурсы
$resources = [
    [
        'pagetitle'     => 'System',
        'template'      => 0,
        'published'     => 1,
        'hidemenu'      => 1,
        'alias'         => 'system',
        'content_type'  => 1,
        'isfolder'      => 1,
        'searchable'    => 0
    ],
    [
        'pagetitle'     => 'sitemap',
        'template'      => 0,
        'published'     => 0,
        'hidemenu'      => 1,
        'alias'         => 'sitemap',
        'content_type'  => 2,
        'richtext'      => 0,
        'content'       => '[[!pdoSitemap]]'
    ],
    [
        'pagetitle'     => 'robots',
        'template'      => 0,
        'published'     => 0,
        'hidemenu'      => 1,
        'alias'         => 'robots',
        'content_type'  => 3,
        'richtext'      => 0,
        'content'       => 'User-agent: * Disallow: /manager/ Disallow: /assets/components/ Allow: /assets/uploads/ Disallow: /core/ Disallow: /connectors/ Disallow: /index.php Disallow: /search Disallow: /profile/ Disallow: *? Host: [[++site_url]] Sitemap: [[++site_url]]sitemap.xml'
    ],
];

// Чанки
$chunks = [
    [
        'name'          => $host[1] . '__head',
        'description'   => '',
        'snippet'       => ''
    ],
    [
        'name'          => $host[1] . '__header',
        'description'   => '',
        'snippet'       => ''
    ],
    [
        'name'          => $host[1] . '__footer',
        'description'   => '',
        'snippet'       => ''
    ],
];


foreach ($settings as $k => $v) {
    $opt = $modx->getObject('modSystemSetting', array('key' => $k));
    if (!empty($opt)) {
        $opt->set('value', $v);
        $opt->save();
        echo 'edited ' . $k . ' = ' . $v . "\n";
    } else {
        $newOpt = $modx->newObject('modSystemSetting');
        $newOpt->set('key', $k);
        $newOpt->set('value', $v);
        $newOpt->save();
        echo 'added ' . $k . ' = ' . $v . "\n";
    }
}

// В циклах обрабатываем и создаем все заданные выше сущности
$i = 0;
foreach ($categories as $attr) {
    if ($cat = $modx->getObject('modCategory', array('category' => $attr['category']))) continue;

    $newCat = $modx->newObject('modCategory');
    $newCat->set('category', $attr['category']);
    $newCat->set('parent', 0);
    $newCat->set('rank', $i);
    $newCat->save();
    $i++;
}

foreach ($resources as $attr) {
    $response = $modx->runProcessor('resource/create', $attr);
}

foreach ($chunks as $attr) {
    $response = $modx->runProcessor('element/chunk/create', $attr);
}

// сбрасываем кэш, чтобы настройки вступили в силу
$modx->runProcessor('system/clearcache');
