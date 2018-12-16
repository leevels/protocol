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
 * (c) 2010-2018 http://queryphp.com All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Leevel\Protocol;

use InvalidArgumentException;
use SplStack;

/**
 * 对象池.
 *
 * @author Xiangmin Liu <635750556@qq.com>
 *
 * @since 2018.12.14
 *
 * @version 1.0
 */
class Pool implements IPool
{
    /**
     * 对象池.
     *
     * @var array
     */
    protected $pools = [];

    /**
     * 构造函数.
     */
    public function __construct()
    {
    }

    /**
     * 获取一个对象.
     *
     * @param string $className
     * @param array  $args
     *
     * @return \Object
     */
    public function get(string $className, ...$args)
    {
        $this->valid($className);
        $className = $this->normalize($className);
        $pool = $this->pool($className);

        if ($pool->count()) {
            return $pool->shift();
        }

        return new $className();
    }

    /**
     * 返还一个对象.
     *
     * @param \Object $obj
     */
    public function back($obj): void
    {
        $className = $this->normalize(get_class($obj));
        $pool = $this->pool($className);
        $pool->push($obj);
    }

    /**
     * 获取对象栈.
     *
     * @param string $className
     *
     * @return \SplStack
     */
    public function pool(string $className): SplStack
    {
        $this->valid($className);
        $className = $this->normalize($className);
        $pool = $this->pools[$className] ?? null;

        if (null !== $pool) {
            return $pool;
        }

        return $this->pools[$className] = new SplStack();
    }

    /**
     * 获取对象池数据.
     *
     * @return array
     */
    public function getPools(): array
    {
        return $this->pools;
    }

    /**
     * 校验类是否存在.
     *
     * @param string $className
     */
    protected function valid(string $className)
    {
        if (!class_exists($className)) {
            return new InvalidArgumentException(sprintf('Class `%s` was not found.', $className));
        }
    }

    /**
     * 统一去掉前面的斜杠.
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function normalize($name)
    {
        return ltrim($name, '\\');
    }
}
