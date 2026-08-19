<?php

namespace App\Core;

class App
{
    private static Container $container;

    public static function setContainer(Container $container)
    {
        self::$container = $container;
    }

    public static function container(): Container
    {
        return self::$container;
    }

    public static function make(string $class)
    {
        return self::$container->make($class);
    }
}
