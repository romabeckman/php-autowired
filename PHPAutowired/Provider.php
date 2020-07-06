<?php

namespace PHPAutowired;

use \Closure;

class Provider {

    /**
     * @var array
     */
    static private $map = [];

    /**
     * @var array
     */
    static private $singleton = [];

    /**
     * @var array
     */
    static private $instances = [];

    /**
     * @param string $class
     * @param Closure $map
     * @return void
     */
    static public function bind(string $class, Closure $map): void {
        static::$map[$class] = $map;
        static::$singleton[$class] = false;
    }

    /**
     * @param string $class
     * @param Closure $map
     * @return void
     */
    static public function singleton(string $class, Closure $map): void {
        static::$map[$class] = $map;
        static::$singleton[$class] = true;
    }

    static public function exist(string $class): bool {
        return isset(static::$map[$class]) || isset(static::$instances[$class]);
    }

    static public function isSingleton(string $class): bool {
        return static::exist($class) && static::$singleton[$class];
    }

    static public function get(string $class) {
        if (isset(static::$instances[$class])) {
            return static::$instances[$class];
        }

        $returned = (static::$map[$class])();

        // Register as singleton
        if (static::$singleton[$class]) {
            unset(static::$map[$class]);
            unset(static::$singleton[$class]);

            static::$instances[$class] = $returned;
        }

        return $returned;
    }

}
