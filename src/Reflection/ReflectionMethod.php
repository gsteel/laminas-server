<?php

/**
 * @see       https://github.com/laminas/laminas-server for the canonical source repository
 */

declare(strict_types=1);

namespace Laminas\Server\Reflection;

use ReflectionClass as PhpReflectionClass;
use ReflectionException;
use ReflectionMethod as PhpReflectionMethod;
use Webmozart\Assert\Assert;

use function array_map;
use function array_merge;
use function implode;
use function str_replace;
use function strpos;

use const PHP_EOL;

class ReflectionMethod extends AbstractFunction
{
    /**
     * Doc block inherit tag for search
     */
    public const INHERIT_TAG = '{@inheritdoc}';

    /**
     * Parent class name
     *
     * @var string
     */
    protected $class;

    /**
     * Parent class reflection
     *
     * @var ReflectionClass
     */
    protected $classReflection;

    public function __construct(
        ReflectionClass $class,
        PhpReflectionMethod $r,
        ?string $namespace = null,
        array $argv = []
    ) {
        $this->classReflection = $class;
        $this->reflection      = $r;

        $classNamespace = $class->getNamespace();

        // Determine namespace
        if (! empty($namespace)) {
            $this->setNamespace($namespace);
        } elseif (! empty($classNamespace)) {
            $this->setNamespace($classNamespace);
        }

        // Determine arguments
        $this->argv = $argv;

        // If method call, need to store some info on the class
        $this->class = $class->getName();
        $this->name  = $r->getName();

        // Perform some introspection
        $this->reflect();
    }

    public function getDeclaringClass(): ReflectionClass
    {
        return $this->classReflection;
    }

    /**
     * Wakeup from serialization
     *
     * Reflection needs explicit instantiation to work correctly. Re-instantiate
     * reflection object on wakeup.
     *
     * @throws ReflectionException
     */
    public function __wakeup(): void
    {
        $this->classReflection = new ReflectionClass(
            new PhpReflectionClass($this->class),
            $this->getNamespace(),
            $this->getInvokeArguments()
        );
        $this->reflection      = new PhpReflectionMethod($this->classReflection->getName(), $this->name);
    }

    protected function reflect(): void
    {
        $docComment = $this->reflection->getDocComment();
        if (false !== $docComment && strpos($docComment, self::INHERIT_TAG) !== false) {
            $this->docComment = $this->fetchRecursiveDocComment();
        }

        parent::reflect();
    }

    /**
     * Fetch all doc comments for inherit values
     */
    private function fetchRecursiveDocComment(): string
    {
        $currentMethodName = $this->reflection->getName();
        $docCommentList[]  = $this->reflection->getDocComment();

        // fetch all doc blocks for method from parent classes
        $docCommentFetched = $this->fetchRecursiveDocBlockFromParent($this->classReflection, $currentMethodName);
        if ($docCommentFetched) {
            $docCommentList = array_merge($docCommentList, $docCommentFetched);
        }

        // fetch doc blocks from interfaces
        $interfaceReflectionList = $this->classReflection->getInterfaces();
        foreach ($interfaceReflectionList as $interfaceReflection) {
            if (! $interfaceReflection->hasMethod($currentMethodName)) {
                continue;
            }

            $docCommentList[] = $interfaceReflection->getMethod($currentMethodName)->getDocComment();
        }

        $normalizedDocCommentList = array_map(
            static function ($docComment) {
                $docComment = str_replace('/**', '', $docComment);
                $docComment = str_replace('*/', '', $docComment);

                return $docComment;
            },
            $docCommentList
        );

        return '/**' . implode(PHP_EOL, $normalizedDocCommentList) . '*/';
    }

    /**
     * @param ReflectionClass|PhpReflectionClass $reflectionClass
     */
    private function fetchRecursiveDocBlockFromParent($reflectionClass, string $methodName): ?array
    {
        $docComment            = [];
        $parentReflectionClass = $reflectionClass->getParentClass();
        if (! $parentReflectionClass) {
            return null;
        }

        Assert::isInstanceOf($parentReflectionClass, PhpReflectionClass::class);

        if (! $parentReflectionClass->hasMethod($methodName)) {
            return null;
        }

        $methodReflection = $parentReflectionClass->getMethod($methodName);
        $docCommentLast   = $methodReflection->getDocComment();
        Assert::string($docCommentLast);

        $docComment[] = $docCommentLast;
        if ($this->isInherit($docCommentLast)) {
            if ($docCommentFetched = $this->fetchRecursiveDocBlockFromParent($parentReflectionClass, $methodName)) {
                $docComment = array_merge($docComment, $docCommentFetched);
            }
        }

        return $docComment;
    }

    private function isInherit(string $docComment): bool
    {
        return strpos($docComment, self::INHERIT_TAG) !== false;
    }
}
