<?php

if (!function_exists('detectProjectBaseUrl')) {
    function detectProjectBaseUrl(): string
    {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName === '') {
            return '';
        }

        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        $scriptDir = ($scriptDir === '.' ? '' : rtrim($scriptDir, '/'));

        if ($scriptDir !== '' && preg_match('#^(.+)/(system|customer)(?:/.*)?$#i', $scriptDir, $m)) {
            $scriptDir = rtrim((string)$m[1], '/');
        }

        return ($scriptDir === '.' ? '' : $scriptDir);
    }
}

if (!defined('PROJECT_BASE_URL')) {
    define('PROJECT_BASE_URL', detectProjectBaseUrl());
}

if (!defined('SYSTEM_BASE_URL')) {
    define('SYSTEM_BASE_URL', (PROJECT_BASE_URL !== '' ? PROJECT_BASE_URL : '') . '/system');
}

if (!function_exists('projectUrl')) {
    function projectUrl(string $relativePath = ''): string
    {
        $relativePath = ltrim($relativePath, '/');
        $base = (PROJECT_BASE_URL !== '' ? PROJECT_BASE_URL : '');
        if ($relativePath === '') {
            return $base !== '' ? $base : '/';
        }
        return ($base !== '' ? $base : '') . '/' . $relativePath;
    }
}

if (!function_exists('projectPathUrl')) {
    function projectPathUrl(string $path): string
    {
        $normalizedPath = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $base = (PROJECT_BASE_URL !== '' ? PROJECT_BASE_URL : '');

        if ($base !== '' && ($normalizedPath === $base || strpos($normalizedPath, $base . '/') === 0)) {
            return $normalizedPath;
        }

        return ($base !== '' ? $base : '') . $normalizedPath;
    }
}

if (!function_exists('systemUrl')) {
    function systemUrl(string $relativePath = ''): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $path = '/system' . ($relativePath !== '' ? '/' . $relativePath : '');
        return projectPathUrl($path);
    }
}
