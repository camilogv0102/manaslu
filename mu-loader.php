<?php
/**
 * Plugin Name: MU Loader – Manaslu
 * Description: Carga archivos MU desde subdirectorios de /mu-plugins.
 */

if (!defined('ABSPATH')) exit;

// 1) Requiere archivos PHP de primer nivel en subcarpetas (p.ej. /extras/*.php)
$dirs = glob(__DIR__ . '/*', GLOB_ONLYDIR);
if ($dirs) {
    foreach ($dirs as $dir) {
        foreach (glob($dir . '/*.php') as $file) {
            require_once $file;
        }
    }
}

// 2) (Opcional) si quieres cargar recursivo (sub-subcarpetas):

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php' && dirname($file) !== __DIR__) {
        require_once $file->getPathname();
    }
}
