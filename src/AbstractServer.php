<?php

/**
 * @see       https://github.com/laminas/laminas-server for the canonical source repository
 */

declare(strict_types=1);

namespace Laminas\Server;

use Laminas\Server\Method\Callback;
use ReflectionClass;
use ReflectionException;
use Webmozart\Assert\Assert;

use function is_object;

abstract class AbstractServer implements ServerInterface
{
    /** @var bool */
    protected $overwriteExistingMethods = false;

    /** @var Definition */
    protected $table;

    public function __construct()
    {
        $this->table = new Definition();
        $this->table->setOverwriteExistingMethods($this->overwriteExistingMethods);
    }

    public function getFunctions(): Definition
    {
        return $this->table;
    }

    /**
     * Build callback for method signature
     */
    private function buildCallback(Reflection\AbstractFunction $reflection): Callback
    {
        $callback = new Callback();
        if ($reflection instanceof Reflection\ReflectionMethod) {
            $callback->setType($reflection->isStatic() ? 'static' : 'instance')
                ->setClass($reflection->getDeclaringClass()->getName())
                ->setMethod($reflection->getName());
        } elseif ($reflection instanceof Reflection\ReflectionFunction) {
            $callback->setType('function')
                ->setFunction($reflection->getName());
        }
        return $callback;
    }

    /**
     * Build a method signature
     *
     * @param  null|string|object $class
     * @throws Exception\RuntimeException On duplicate entry.
     */
    final protected function buildSignature(Reflection\AbstractFunction $reflection, $class = null): Method\Definition
    {
        $ns     = $reflection->getNamespace();
        $name   = $reflection->getName();
        $method = empty($ns) ? $name : $ns . '.' . $name;

        if (! $this->overwriteExistingMethods && $this->table->hasMethod($method)) {
            throw new Exception\RuntimeException('Duplicate method registered: ' . $method);
        }

        $definition = new Method\Definition();
        $definition->setName($method)
            ->setCallback($this->buildCallback($reflection))
            ->setMethodHelp($reflection->getDescription())
            ->setInvokeArguments($reflection->getInvokeArguments());

        foreach ($reflection->getPrototypes() as $proto) {
            $prototype = new Method\Prototype();
            $prototype->setReturnType($this->fixType($proto->getReturnType()));
            foreach ($proto->getParameters() as $parameter) {
                $param = new Method\Parameter([
                    'type'     => $this->fixType($parameter->getType()),
                    'name'     => $parameter->getName(),
                    'optional' => $parameter->isOptional(),
                ]);
                if ($parameter->isDefaultValueAvailable()) {
                    $param->setDefaultValue($parameter->getDefaultValue());
                }
                $prototype->addParameter($param);
            }
            $definition->addPrototype($prototype);
        }
        if (is_object($class)) {
            $definition->setObject($class);
        }
        $this->table->addMethod($definition);
        return $definition;
    }

    /**
     * Dispatch method
     *
     * @return mixed
     * @throws ReflectionException
     */
    protected function dispatch(Method\Definition $invokable, array $params)
    {
        $callback = $invokable->getCallback();
        Assert::isInstanceOf($callback, Callback::class);

        $type = $callback->getType();
        if ('function' === $type) {
            $function = $callback->getFunction();
            Assert::isCallable($function);
            return $function(...$params);
        }

        $class  = $callback->getClass();
        $method = $callback->getMethod();

        if ('static' === $type) {
            $callback = [$class, $method];
            return $callback(...$params);
        }

        $object = $invokable->getObject();
        if (! is_object($object)) {
            $invokeArgs = $invokable->getInvokeArguments();
            if (! empty($invokeArgs)) {
                $reflection = new ReflectionClass($class);
                $object     = $reflection->newInstanceArgs($invokeArgs);
            } else {
                Assert::isString($class);
                Assert::classExists($class);
                /** @psalm-suppress MixedMethodCall */
                $object = new $class();
            }
        }
        $callback = [$object, $method];
        return $callback(...$params);
    }

    /**
     * Map PHP type to protocol type
     */
    abstract protected function fixType(string $type): string;
}
