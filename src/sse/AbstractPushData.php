<?php
/**
 * This file is part of monda-worker.
 *
 * @contact  mondagroup_php@163.com
 *
 */
namespace framework\sse;

/**
 * Class AbstractPushData
 * @package framework\sse
 */
abstract class AbstractPushData
{
    public $start;

    /**
     * @return array
     */
    abstract public function pushData(): array;
}
