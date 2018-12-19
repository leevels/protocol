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

namespace Leevel\Protocol\Console;

use Leevel\Leevel\Facade\Leevel;
use Leevel\Protocol\Console\Base\Stop as BaseStop;
use Leevel\Protocol\IServer;

/**
 * Http 服务停止.
 *
 * @author Xiangmin Liu <635750556@qq.com>
 *
 * @since 2017.12.26
 *
 * @version 1.0
 */
class HttpStop extends BaseStop
{
    /**
     * 命令名字.
     *
     * @var string
     */
    protected $name = 'http:stop';

    /**
     * 命令行描述.
     *
     * @var string
     */
    protected $description = 'Stop http service';

    /**
     * 创建 server.
     *
     * @return \Leevel\Protocol\IServer
     */
    protected function createServer(): IServer
    {
        return Leevel::make('http.server');
    }

    /**
     * 返回 Version.
     *
     * @return string
     */
    protected function getVersion(): string
    {
        return 'Http Stop Version '.Leevel::version().PHP_EOL;
    }
}
