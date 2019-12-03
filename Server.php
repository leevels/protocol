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
 * (c) 2010-2020 http://queryphp.com All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Leevel\Protocol;

use InvalidArgumentException;
use Leevel\Di\IContainer;
use Leevel\Di\ICoroutine;
use Leevel\Filesystem\Fso\create_directory;
use function Leevel\Filesystem\Fso\create_directory;
use Leevel\Filesystem\Fso\create_file;
use function Leevel\Filesystem\Fso\create_file;
use Leevel\Protocol\Process as ProtocolProcess;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server as SwooleServer;
use Throwable;

/**
 * Swoole 服务基类.
 *
 * @author Xiangmin Liu <635750556@qq.com>
 *
 * @since 2017.12.25
 * @see https://www.swoole.com/
 * @see https://www.cnblogs.com/luojianqun/p/5355439.html
 *
 * @version 1.0
 * @codeCoverageIgnore
 */
abstract class Server
{
    /**
     * IOC 容器.
     *
     * @var \Leevel\Di\IContainer
     */
    protected IContainer $container;

    /**
     * swoole 服务实例.
     *
     * @var \Swoole\Server
     */
    protected SwooleServer $server;

    /**
     * 配置.
     *
     * @var array
     */
    protected array $option = [];

    /**
     * 服务回调事件.
     *
     * @var array
     */
    protected array $serverEvent = [
        'start',
        'connect',
        'workerStart',
        'managerStart',
        'workerStop',
        'receive',
        'task',
        'finish',
        'shutdown',
        'close',
    ];

    /**
     * 构造函数.
     */
    public function __construct(IContainer $container, ICoroutine $coroutine, array $option = [])
    {
        $this->validSwoole();
        $container->setCoroutine($coroutine);
        $this->container = $container;
        $this->option = array_merge($this->option, $option);
    }

    /**
     * call.
     *
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        return $this->server->{$method}(...$args);
    }

    /**
     * 设置配置.
     *
     * @param mixed $value
     *
     * @return \Leevel\Protocol\IServer
     */
    public function setOption(string $name, $value): IServer
    {
        $this->option[$name] = $value;

        return $this;
    }

    /**
     * 获取配置.
     */
    public function getOption(): array
    {
        return $this->option;
    }

    /**
     * 添加自定义进程.
     *
     * @throws \InvalidArgumentException
     */
    public function process(string $process): void
    {
        $newProgress = new Process(
            function (Process $worker) use ($process) {
                $newProgress = $this->container->make($process);
                if (!is_object($newProgress) || ($newProgress instanceof ProtocolProcess)) {
                    $e = sprintf('Process `%s` was invalid.', $process);

                    throw new InvalidArgumentException($e);
                }

                if (!is_callable([$newProgress, 'handle'])) {
                    $e = sprintf('The `handle` of process `%s` was not found.', $process);

                    throw new InvalidArgumentException($e);
                }

                $processName = $this->option['process_name'].'.'.$newProgress->getName();
                $worker->name($processName);
                $newProgress->handle($this, $worker);
            }
        );

        $this->server->addProcess($newProgress);
    }

    /**
     * 运行服务.
     */
    public function startServer(): void
    {
        $this->checkPidPath();
        $this->createServer();
        $this->eventServer();
        $this->startSwooleServer();
    }

    /**
     * 返回服务.
     */
    public function getServer(): SwooleServer
    {
        return $this->server;
    }

    /**
     * 主进程的主线程.
     *
     * - 记录进程 id,脚本实现自动重启.
     *
     * @throws \InvalidArgumentException
     *
     * @see https://wiki.swoole.com/wiki/page/p-event/onStart.html
     */
    public function onStart(SwooleServer $server): void
    {
        $message = sprintf(
            'Server is started at %s:%d',
            $this->option['host'], $this->option['port']
        );
        $this->log($message, true, '');
        $this->log('Server master worker start.', true);

        $this->setProcessName($this->option['process_name'].'.master');
        $pidContent = $server->master_pid."\n".$server->manager_pid;
        create_file($this->option['pid_path'], $pidContent);
    }

    /**
     * 新的连接进入时.
     *
     * - 每次连接时(相当于每个浏览器第一次打开页面时)执行一次, reload 时连接不会断开, 也就不会再次触发该事件.
     *
     * @see https://wiki.swoole.com/wiki/page/49.html
     */
    public function onConnect(SwooleServer $server, int $fd, int $reactorId): void
    {
        $message = sprintf(
            'Server connect, fd %d, reactorId %d.',
            $fd, $reactorId
        );
        $this->log($message);
    }

    /**
     * worker start 加载业务脚本常驻内存.
     *
     * - 由于服务端命令行也采用 QueryPHP,无需再次引入 QueryPHP
     * - 每个 Worker 进程启动或重启时都会执行.
     *
     * @see https://wiki.swoole.com/wiki/page/p-event/onWorkerStart.html
     */
    public function onWorkerStart(SwooleServer $server, int $workeId): void
    {
        if ($workeId >= $this->option['worker_num']) {
            $this->setProcessName($this->option['process_name'].'.task');
        } else {
            $this->setProcessName($this->option['process_name'].'.worker');
        }

        // 开启 opcache 重连后需要刷新
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }

        $this->enableCoroutine();
    }

    /**
     * 当管理进程启动时调用.
     *
     * - 服务器启动时执行一次.
     *
     * @see https://wiki.swoole.com/wiki/page/190.html
     */
    public function onManagerStart(SwooleServer $server): void
    {
        $this->log('Server manager worker start.', true);
        $message = sprintf('Master Pid: %d,Manager Pid: %d.', $server->master_pid, $server->manager_pid);
        $this->log($message, true);
        $this->setProcessName($this->option['process_name'].'.manager');
    }

    /**
     * worker进程终止时发生
     */
    public function onWorkerStop(SwooleServer $server, int $workerId): void
    {
        $message = sprintf(
            'Server %s worker %d shutdown',
            $server->setting['process_name'], $workerId
        );
        $this->log($message);
    }

    /**
     * 监听数据发送事件.
     *
     * @see https://wiki.swoole.com/wiki/page/50.html
     */
    public function onReceive(SwooleServer $server, int $fd, int $reactorId, string $data): void
    {
        $server->task($data);
        $server->send($fd, 'Task is ok');
    }

    /**
     * 监听连接 Finish 事件.
     *
     * @see https://wiki.swoole.com/wiki/page/136.html
     */
    public function onFinish(SwooleServer $server, int $taskId, string $data): void
    {
        $message = sprintf('Task %d finish, the result is %s', $taskId, $data);
        $this->log($message);
    }

    /**
     * 监听连接 task 事件.
     *
     * @throws \InvalidArgumentException
     *
     * @see https://wiki.swoole.com/wiki/page/134.html
     */
    public function onTask(SwooleServer $server, int $taskId, int $fromId, string $data): void
    {
        $message = sprintf(
            'Task %d form workder %d, the result is %s',
            $taskId, $fromId, $data
        );
        $this->log($message);

        list($task, $params) = $this->parseTask($data);
        if (false !== strpos($task, '@')) {
            list($task, $method) = explode('@', $task);
        } else {
            $method = 'handle';
        }

        if (!is_object($task = $this->container->make($task))) {
            throw new InvalidArgumentException('Task is invalid.');
        }

        $params[] = $server;
        $params[] = $taskId;
        $params[] = $fromId;

        try {
            $task->{$method}(...$params);
            $server->finish($data);
        } catch (Throwable $th) {
            // @todo 优化
        }
    }

    /**
     * Server 正常结束时发生.
     *
     * - 服务器关闭时执行一次.
     *
     * @see https://wiki.swoole.com/wiki/page/p-event/onShutdown.html
     */
    public function onShutdown(SwooleServer $server): void
    {
        if (is_file($this->option['pid_path'])) {
            unlink($this->option['pid_path']);
        }

        $this->log('Server shutdown');
    }

    /**
     * 开启协程 Hook.
     */
    protected function enableCoroutine(): void
    {
        Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
    }

    /**
     * 解析任务.
     */
    protected function parseTask(string $task): array
    {
        list($task, $params) = array_pad(explode(':', $task, 2), 2, []);
        if (is_string($params)) {
            $params = explode(',', $params);
        }

        $params = array_map(function (string $item) {
            return ctype_digit($item) ? (int) $item :
                (is_numeric($item) ? (float) $item : $item);
        }, $params);

        return [$task, $params];
    }

    /**
     * 清理协程上下文数据.
     */
    protected function removeCoroutine(): void
    {
        $this->container->removeCoroutine();
    }

    /**
     * 验证 pid_path 是否可用.
     *
     * @throws \InvalidArgumentException
     */
    protected function checkPidPath(): void
    {
        if (!$this->option['pid_path']) {
            throw new InvalidArgumentException('Pid path is not set');
        }

        create_directory(dirname($this->option['pid_path']));
    }

    /**
     * 创建 server.
     */
    protected function createServer(): void
    {
        $this->server = new SwooleServer(
            $this->option['host'],
            (int) ($this->option['port'])
        );

        $this->initServer();
    }

    /**
     * 初始化 http server.
     */
    protected function initServer(): void
    {
        $this->server->set($this->option);

        foreach ($this->option['processes'] as $process) {
            $this->process($process);
        }

        $this->container->instance('server', $this->server);

        // 删除容器中注册为 -1 的数据
        $this->removeCoroutine();
    }

    /**
     * HTTP Server 绑定事件.
     */
    protected function eventServer(): void
    {
        $type = get_class($this);
        $type = substr($type, strrpos($type, '\\') + 1);
        $type = str_replace('Server', '', $type);

        foreach ($this->serverEvent as $event) {
            if (!method_exists($this, $onEvent = 'on'.$type.ucfirst($event))) {
                $onEvent = 'on'.ucfirst($event);
            }
            $this->server->on($event, [$this, $onEvent]);
        }
    }

    /**
     * HTTP Server 启动.
     */
    protected function startSwooleServer(): void
    {
        $this->server->start();
    }

    /**
     * 设置 swoole 进程名称.
     *
     * @throws \InvalidArgumentException
     *
     * @see http://php.net/manual/zh/function.cli-set-process-title.php
     * @see https://wiki.swoole.com/wiki/page/125.html
     */
    protected function setProcessName(string $name): void
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } else {
            if (function_exists('swoole_set_process_name')) {
                swoole_set_process_name($name);
            } else {
                $e = 'Require cli_set_process_title or swoole_set_process_name.';

                throw new InvalidArgumentException($e);
            }
        }
    }

    /**
     * 是否为守候进程运行.
     */
    protected function daemonize(): bool
    {
        return !$this->option['daemonize'];
    }

    /**
     * 记录日志.
     */
    protected function log(string $message, bool $force = false, string $formatTime = 'H:i:s'): void
    {
        if (!$force && !$this->daemonize()) {
            return;
        }

        fwrite(STDOUT, $this->messageTime($message, $formatTime).PHP_EOL);
    }

    /**
     * 消息时间.
     */
    protected function messageTime(string $message, string $formatTime = ''): string
    {
        return ($formatTime ? sprintf('[%s]', date($formatTime)) : '').$message;
    }

    /**
     * 验证 swoole 版本.
     *
     * @throws \InvalidArgumentException
     */
    protected function validSwoole(): void
    {
        if (!extension_loaded('swoole')) {
            $e = 'Swoole was not installed.';

            throw new InvalidArgumentException($e);
        }

        if (version_compare(phpversion('swoole'), '4.4.5', '<')) {
            $e = 'Swoole 4.4.5 OR Higher';

            throw new InvalidArgumentException($e);
        }
    }
}

// import fn.
class_exists(create_directory::class); // @codeCoverageIgnore
class_exists(create_file::class); // @codeCoverageIgnore
