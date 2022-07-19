<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit686c8a64b59583ec4f4bbb8c9c66e4c9
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'C' => 
        array (
            'ChrisKonnertz\\StringCalc' => 
            array (
                0 => __DIR__ . '/..' . '/chriskonnertz/string-calc/src',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit686c8a64b59583ec4f4bbb8c9c66e4c9::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit686c8a64b59583ec4f4bbb8c9c66e4c9::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit686c8a64b59583ec4f4bbb8c9c66e4c9::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit686c8a64b59583ec4f4bbb8c9c66e4c9::$classMap;

        }, null, ClassLoader::class);
    }
}
