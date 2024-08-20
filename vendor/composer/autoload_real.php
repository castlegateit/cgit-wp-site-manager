<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInita209243b3ff7c73fbd9554ca01dddb3b
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInita209243b3ff7c73fbd9554ca01dddb3b', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInita209243b3ff7c73fbd9554ca01dddb3b', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInita209243b3ff7c73fbd9554ca01dddb3b::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}