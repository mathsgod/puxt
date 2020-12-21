<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit5db8d6b7cc11e4bd2c13f7281b57bf00
{
    public static $files = array (
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
        '2522c76baa4d94ac01eccc243a9c91bc' => __DIR__ . '/..' . '/mathsgod/php-psr7/function/function.php',
    );

    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'Twig\\' => 5,
        ),
        'S' => 
        array (
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Polyfill\\Ctype\\' => 23,
        ),
        'P' => 
        array (
            'Psr\\Http\\Message\\' => 17,
            'PUXT\\' => 5,
            'PHP\\Psr7\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Twig\\' => 
        array (
            0 => __DIR__ . '/..' . '/twig/twig/src',
        ),
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Polyfill\\Ctype\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-ctype',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'PUXT\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'PHP\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/mathsgod/php-psr7/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'T' => 
        array (
            'Twig_' => 
            array (
                0 => __DIR__ . '/..' . '/twig/twig/lib',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit5db8d6b7cc11e4bd2c13f7281b57bf00::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit5db8d6b7cc11e4bd2c13f7281b57bf00::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit5db8d6b7cc11e4bd2c13f7281b57bf00::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit5db8d6b7cc11e4bd2c13f7281b57bf00::$classMap;

        }, null, ClassLoader::class);
    }
}