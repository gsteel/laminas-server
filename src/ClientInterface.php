<?php

/**
 * @see       https://github.com/laminas/laminas-server for the canonical source repository
 * @copyright https://github.com/laminas/laminas-server/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-server/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Laminas\Server;

interface ClientInterface
{
    /**
     * Executes remote call
     *
     * Unified interface for calling custom remote methods.
     *
     * @param  string $method Remote call name.
     * @param  array  $params Call parameters.
     * @return mixed Remote call results.
     */
    public function call(string $method, array $params = []);
}
