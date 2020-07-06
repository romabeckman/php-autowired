<?php

namespace PHPAutowired;

use \DomainException;
use \ReflectionClass;
use \ReflectionException;
use \ReflectionMethod;

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

    static function setAutoInjectionAttributes(bool $autoInjectionAttributes): void {
        static::$autoInjectionAttributes = $autoInjectionAttributes;
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
        try {
            $reflectionMethod = new ReflectionMethod($this->class, $method);

            if ($reflectionMethod->isPublic() === false) {
                throw new DomainException('Method is not accessible');
            }

            $params = $this->getDependencies($reflectionMethod, $params);

            if ($reflectionMethod->isConstructor()) {
                return (new ReflectionClass($this->class))->newInstanceArgs($params);
            } else {
                return $reflectionMethod->invokeArgs($this->getInstance(), $params);
            }
        } catch (ReflectionException $exc) {
            if ($method == '__construct') {
                return new $this->class;
            }

            throw new DomainException('Method called not exist');
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
            $class = $parameters->getClass();

            if (isset($params[$parameters->getName()])) {
                $args[$parameters->getName()] = $params[$parameters->getName()];
            } elseif ($class && $class->isInstantiable()) {
                $args[$parameters->getName()] = (new Autowired($class->getName()))->getInstance();
            }
        }

        return $args;
    }

}
