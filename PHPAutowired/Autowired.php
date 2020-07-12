<?php

namespace PHPAutowired;

use \DomainException;
use \ReflectionClass;
use \ReflectionException;
use \ReflectionMethod;

/**
 * Dependency injection
 */
class Autowired {

    /**
     * Apply auto injection in attributes of class
     *
     * @var bool
     */
    static private $autoInjectionAttributes = true;

    /**
     * @var object
     */
    private $instance;

    /**
     * @var string
     */
    private $class;

    /**
     *
     * @param object|string $class
     */
    function __construct($class) {
        $this->class = is_object($class) ?
                get_class($class) :
                $class;

        $this->instance = $this->loadConstructor($class);

        if (Autowired::$autoInjectionAttributes) {
            $this->injectInAttributes();
        }
    }

    /**
     * @return object
     */
    public function getInstance() {
        return $this->instance;
    }

    /**
     * @param string $method
     * @param type $params
     * @return \App\Libraries\class
     * @throws DomainException
     */
    public function invokeMethod(string $method, array $params = []) {
        $reflection = new ReflectionClass($this->class);

        try {
            $reflectionMethod = $reflection->getMethod($method);

            if ($reflectionMethod->isPublic() === false && $method != '__construct') {
                throw new DomainException('Method is not accessible');
            }

            $params = $this->getDependencies($reflectionMethod, $params);

            if ($reflectionMethod->isConstructor()) {
                return $reflection->newInstanceArgs($params);
            } else {
                return $reflectionMethod->invokeArgs($this->getInstance(), $params);
            }
        } catch (ReflectionException $exc) {
            if ($method == '__construct') {
                return $reflection->newInstanceWithoutConstructor();
            }

            throw new DomainException('The method "' . $method . '" called not exist in "' . $this->class . '" class.');
        }
    }

    /**
     * Inject object in attributes
     *
     * @return void
     */
    private function injectInAttributes(): void {
        $reflection = new ReflectionClass($this->class);
        foreach ($reflection->getProperties() as $property) {
            $document = $property->getDocComment();

            if (preg_match('/\@autowired/iu', $document)) {
                $class = null;

                // PHP >= 7.4
                $type = $property->getType();
                if ($type && $type->isBuiltin() == false) {
                    $class = $type->getName();
                }
                // PHP < 7.4
                elseif (preg_match('/@var\s+([^\s]+)/', $document, $matches)) {
                    $class = $matches[1];
                }

                if (is_null($class)) {
                    throw new DomainException('Document or type is missing in attribute ' . $property->getName());
                }

                $property->setAccessible(true);
                $property->setValue(
                        $this->getInstance(),
                        (new Autowired($class))->getInstance()
                );
            }
        }
    }

    /**
     * @param object|string $class
     * @return object
     */
    private function loadConstructor($class) {
        if (is_object($class)) {
            return $class;
        } elseif (Provider::exist($class)) {
            return Provider::get($class);
        } else {
            return $this->invokeMethod('__construct');
        }
    }

    /**
     * @param ReflectionMethod $reflectionMethod
     * @return array
     */
    private function getDependencies(ReflectionMethod $reflectionMethod, array $params): array {
        if ($reflectionMethod->getNumberOfParameters() === 0) {
            return [];
        }

        $args = [];
        foreach ($reflectionMethod->getParameters() as $parameters) {
            if (isset($params[$parameters->getName()])) {
                $args[$parameters->getName()] = $params[$parameters->getName()];
            } elseif ($class = $parameters->getClass()) {
                $class->isInstantiable() && $args[$parameters->getName()] = static::new($class->getName());
            } else {
                $args[$parameters->getName()] = array_shift($params);
            }
        }

        return $args;
    }

    /**
     *
     * @param bool $autoInjectionAttributes
     * @return void
     */
    static function setAutoInjectionAttributes(bool $autoInjectionAttributes): void {
        static::$autoInjectionAttributes = $autoInjectionAttributes;
    }

    /**
     *
     * @param string $class
     * @return object
     */
    static public function new(string $class) {
        return (new static($class))->getInstance();
    }

}
