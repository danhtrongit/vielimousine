<?php
declare(strict_types=1);

namespace Vie;

final class Container
{
    private static array $instances = [];

    public static function get(string $class): object
    {
        if (isset(self::$instances[$class])) {
            return self::$instances[$class];
        }

        $reflection  = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return self::$instances[$class] = new $class();
        }

        $deps = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $deps[] = self::get($type->getName());
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $deps[] = $param->getDefaultValue();
                continue;
            }
            throw new \LogicException("Cannot auto-wire param \${$param->getName()} in {$class}");
        }

        return self::$instances[$class] = $reflection->newInstanceArgs($deps);
    }
}
