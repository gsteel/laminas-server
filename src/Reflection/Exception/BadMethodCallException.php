<?php

/**
 * @see       https://github.com/laminas/laminas-server for the canonical source repository
 */

declare(strict_types=1);

namespace Laminas\Server\Reflection\Exception;

use Laminas\Server\Exception;

class BadMethodCallException extends Exception\BadMethodCallException implements ExceptionInterface
{
}
