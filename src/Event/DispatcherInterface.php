<?php
/**
 * This file is part of graze/parallel-process.
 *
 * Copyright © 2018 Nature Delivered Ltd. <https://www.graze.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://github.com/graze/parallel-process/blob/master/LICENSE.md
 * @link    https://github.com/graze/parallel-process
 */

namespace Graze\ParallelProcess\Event;

use Psr\EventDispatcher\StoppableEventInterface;

interface DispatcherInterface
{
    /**
     * @param string   $name
     * @param callable $handler
     *
     * @return $this
     */
    public function addListener(string $name, callable $handler);

    /**
     * @param string $name
     * @param StoppableEventInterface $event
     * @return mixed
     */
    public function dispatch(string $name, StoppableEventInterface $event);
}
