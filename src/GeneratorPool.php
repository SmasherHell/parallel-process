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

namespace Graze\ParallelProcess;

use Closure;
use Exception;
use InvalidArgumentException;
use Psr\EventDispatcher\StoppableEventInterface;
use SplQueue;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Allow a PoolInterface to be driven by a generator function.
 * Useful whenever you need to drive a lot of sub-processes.
 * GeneratorPool can help you manage memory consumption in that case.
 *
 * Process pool resolve is delegated to decorated PoolInterface.
 *
 */
class GeneratorPool implements PoolInterface, RunInterface
{
    use RunningStateTrait;

    /** @var PoolInterface|RunInterface */
    private $decorated;

    /** @var Closure[]|SplQueue  */
    private $generators = [];

    /**
     * GeneratorPool constructor.
     * @param PoolInterface $decorated
     * @param Closure $generator
     */
    public function __construct(PoolInterface $decorated, Closure $generator = null)
    {
        /** @var RunInterface $decorated */
        if ($decorated->hasStarted()) {
            throw new \RuntimeException("Unable to decorate a running PoolInterface");
        }
        $this->decorated = $decorated;
        $this->generators = new SplQueue();

        if (null !== $generator) {
            $this->generators->enqueue($generator);
        }
    }

    /**
     * @param RunInterface|Process|Closure $item
     * @param array $tags
     * @return PoolInterface
     *
     * @throws InvalidArgumentException
     */
    public function add($item, array $tags = [])
    {
        // Adding a generator
        if ($item instanceof Closure) {
            $this->generators->enqueue($item);
            return $this;
        }

        // Adding an item directly to decorated
        return $this->decorated->add($item, $tags);
    }

    /**
     * @return PoolInterface|RunInterface
     */
    public function start()
    {
        // PriorityPool should  just use run to autostack process in generators
        if ($this->decorated instanceof PriorityPool) {
            $this->run();
            return $this;
        }

        while (!$this->generators->isEmpty() && $generator = $this->generators->dequeue()) {
            foreach ($generator() as $run) {
                $this->decorated->add($run);
            }
        }

        return $this->decorated->start();
    }

    /**
     * @return bool
     */
    public function poll()
    {
        // Maintain a waiting stack
        if ($this->decorated instanceof PriorityPool
            && $this->decorated->getMaxSimultaneous() !== PriorityPool::NO_MAX
            && count($this->decorated->getWaiting()) < $this->decorated->getMaxSimultaneous()
        ) {
            return false;
        }

        return $this->decorated->poll();
    }

    /**
     * @param float $interval
     * @return bool
     */
    public function run($interval = self::CHECK_INTERVAL)
    {
        // Not a PriorityPool must be started explicitly
        if (!$this->decorated instanceof PriorityPool) {
            $this->start();
        }

        $interval = (int) ($interval * 1000000);

        while (!$this->generators->isEmpty() && $generator = $this->generators->dequeue()) {
            foreach (($generator)() as $item) {
                $this->decorated->add($item);
                while ($this->poll()) {
                    usleep($interval);
                }
            }
        }

        // Let's finish remaining task
        while ($this->decorated->poll()) {
            usleep($interval);
        }

        return $this->isSuccessful();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return mixed[]
     */
    public function getAll()
    {
        return $this->decorated->getAll();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return RunInterface[]
     */
    public function getWaiting()
    {
        return $this->decorated->getWaiting();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return RunInterface[]
     */
    public function getRunning()
    {
        return $this->decorated->getRunning();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return RunInterface[]
     */
    public function getFinished()
    {
        return $this->decorated->getFinished();
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->generators->count();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @param string $name
     * @param callable $handler
     * @return PoolInterface|RunInterface
     */
    public function addListener($name, callable $handler)
    {
        return $this->decorated->addListener($name, $handler);
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return bool
     */
    public function hasStarted()
    {
        return $this->decorated->hasStarted();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->decorated->isSuccessful();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return Exception[]|Throwable[]
     */
    public function getExceptions()
    {
        return $this->decorated->getExceptions();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return bool
     */
    public function isRunning()
    {
        return $this->decorated->isRunning();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return array
     */
    public function getTags()
    {
        return $this->decorated->getTags();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return float[]|null
     */
    public function getProgress()
    {
        return $this->decorated->getProgress();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @return float
     */
    public function getPriority()
    {
        return $this->decorated->getPriority();
    }

    /**
     * @codeCoverageIgnore covered with tests of decorated service
     * @param string $name
     * @param StoppableEventInterface $event
     * @return mixed|void
     */
    public function dispatch(string $name, StoppableEventInterface $event)
    {
        $this->decorated->dispatch($name, $event);
    }
}
