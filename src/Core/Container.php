<?php

namespace App\Core;

use Exception;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $key, callable $resolver)
    {
        $this->bindings[$key] = $resolver;
    }

    public function singleton(string $key, callable $resolver)
    {
        $this->bindings[$key] = function ($container) use ($resolver, $key) {
            if (!isset($this->instances[$key])) {
                $this->instances[$key] = $resolver($container);
            }
            return $this->instances[$key];
        };
    }

    public function make(string $key)
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (isset($this->bindings[$key])) {
            return $this->bindings[$key]($this);
        }

        // auto resolve
        if (!class_exists($key)) {
            throw new Exception("Cannot resolve {$key}");
        }

        $reflection = new \ReflectionClass($key);

        if (!$reflection->getConstructor()) {
            return new $key;
        }

        $params = [];

        foreach ($reflection->getConstructor()->getParameters() as $param) {
            $type = $param->getType();

            if (!$type) {
                throw new Exception("Cannot resolve parameter {$param->getName()}");
            }

            $params[] = $this->make($type->getName());
        }

        return $reflection->newInstanceArgs($params);
    }
}
