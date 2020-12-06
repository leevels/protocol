<?php

declare(strict_types=1);

namespace Leevel\Protocol\Pool;

/**
 * 连接池连接驱动接口.
 */
interface IConnection
{
    /**
     * 归还连接池.
     */
    public function release(): void;

    /**
     * 设置是否归还连接池.
     */
    public function setRelease(bool $release): void;

    /**
     * 设置关联连接池.
     */
    public function setPool(IPool $pool): void;

    /**
     * 关闭连接.
     */
    public function close(): void;
}
