<?php

/**
 * PHPUnit bootstrap.
 *
 * The package is a Laravel add-on but the test suite runs without a full
 * framework, so the handful of global helpers the code under test relies on are
 * defined here (guarded, in case a future full-framework run provides them).
 */

require __DIR__ . '/../vendor/autoload.php';

if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        $container = \Illuminate\Container\Container::getInstance();

        if ($container && $container->bound('config')) {
            $repo = $container->make('config');

            if (is_null($key)) {
                return $repo;
            }

            return is_array($key) ? $repo->set($key) : $repo->get($key, $default);
        }

        // No framework config bound (plain unit tests): minimal static fallback.
        static $fallback = [
            'cloner.should_clone_media' => false,
            'cloner.trait_cloneable_relations' => [],
        ];

        if (is_null($key)) {
            return $fallback;
        }

        if (is_array($key)) {
            $fallback = array_merge($fallback, $key);

            return null;
        }

        return array_key_exists($key, $fallback) ? $fallback[$key] : $default;
    }
}

if (!function_exists('now')) {
    function now($tz = null)
    {
        return \Illuminate\Support\Carbon::now($tz);
    }
}

if (!function_exists('base_path')) {
    function base_path($path = '')
    {
        return dirname(__DIR__) . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('config_path')) {
    function config_path($path = '')
    {
        return base_path('config' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}

if (!function_exists('database_path')) {
    function database_path($path = '')
    {
        return base_path('database' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
    }
}
