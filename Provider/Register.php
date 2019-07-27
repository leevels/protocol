<?php

declare(strict_types=1);

/*
 * This file is part of the ************************ package.
 * _____________                           _______________
 *  ______/     \__  _____  ____  ______  / /_  _________
 *   ____/ __   / / / / _ \/ __`\/ / __ \/ __ \/ __ \___
 *    __/ / /  / /_/ /  __/ /  \  / /_/ / / / / /_/ /__
 *      \_\ \_/\____/\___/_/   / / .___/_/ /_/ .___/
 *         \_\                /_/_/         /_/
 *
 * The PHP Framework For Code Poem As Free As Wind. <Query Yet Simple>
 * (c) 2010-2019 http://queryphp.com All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Leevel\Protocol\Provider;

use Leevel\Di\IContainer;
use Leevel\Di\ICoroutine;
use Leevel\Di\Provider;
use Leevel\Protocol\Client\Rpc;
use Leevel\Protocol\Coroutine;
use Leevel\Protocol\HttpServer;
use Leevel\Protocol\ITask;
use Leevel\Protocol\ITimer;
use Leevel\Protocol\RpcServer;
use Leevel\Protocol\Task;
use Leevel\Protocol\Timer;
use Leevel\Protocol\WebsocketServer;

/**
 * swoole 服务提供者.
 *
 * @author Xiangmin Liu <635750556@qq.com>
 *
 * @since 2017.12.21
 *
 * @version 1.0
 * @codeCoverageIgnore
 */
class Register extends Provider
{
    /**
     * 注册服务.
     */
    public function register(): void
    {
        $this->coroutine();
        $this->httpServer();
        $this->websocketServer();
        $this->rpcServer();
        $this->rpc();
        $this->task();
        $this->timer();
    }

    /**
     * 可用服务提供者.
     *
     * @return array
     */
    public static function providers(): array
    {
        return [
            'coroutine'        => [ICoroutine::class, Coroutine::class],
            'http.server'      => HttpServer::class,
            'websocket.server' => WebsocketServer::class,
            'rpc.server'       => RpcServer::class,
            'pool'             => [IPool::class, Pool::class],
            'rpc'              => Rpc::class,
            'task'             => [ITask::class, Task::class],
            'timer'            => [ITimer::class, Timer::class],
        ];
    }

    /**
     * 是否延迟载入.
     *
     * @return bool
     */
    public static function isDeferred(): bool
    {
        return true;
    }

    /**
     * 注册 coroutine 服务.
     */
    protected function coroutine(): void
    {
        $this->container
            ->singleton(
                'coroutine',
                function (): Coroutine {
                    return new Coroutine();
                },
            );
    }

    /**
     * 注册 http.server 服务.
     */
    protected function httpServer(): void
    {
        $this->container
            ->singleton(
                'http.server',
                function (IContainer $container): HttpServer {
                    return new HttpServer(
                        $container,
                        $container->make('coroutine'),
                        array_merge(
                            $container['option']['protocol\\server'],
                            $container['option']['protocol\\http']
                        )
                    );
                },
            );
    }

    /**
     * 注册 websocket.server 服务.
     */
    protected function websocketServer(): void
    {
        $this->container
            ->singleton(
                'websocket.server',
                function (IContainer $container): WebsocketServer {
                    return new WebsocketServer(
                        $container,
                        $container->make('coroutine'),
                        array_merge(
                            $container['option']['protocol\\server'],
                            $container['option']['protocol\\websocket']
                        )
                    );
                },
            );
    }

    /**
     * 注册 rpc.server 服务.
     */
    protected function rpcServer(): void
    {
        $this->container
            ->singleton(
                'rpc.server',
                function (IContainer $container): RpcServer {
                    return new RpcServer(
                        $container,
                        $container->make('coroutine'),
                        array_merge(
                            $container['option']['protocol\\server'],
                            $container['option']['protocol\\rpc']
                        )
                    );
                },
            );
    }

    /**
     * 注册 rpc 服务.
     */
    protected function rpc(): void
    {
        $this->container
            ->singleton(
                'rpc',
                function (): Rpc {
                    return new Rpc();
                },
            );
    }

    /**
     * 注册 task 服务.
     */
    protected function task(): void
    {
        $this->container
            ->singleton(
                'task',
                function (IContainer $container): Task {
                    return new Task($container->make('server'));
                },
            );
    }

    /**
     * 注册 timer 服务.
     */
    protected function timer(): void
    {
        $this->container
            ->singleton(
                'timer',
                function (IContainer $container): Timer {
                    return new Timer($container->make('logs'));
                },
            );
    }
}
