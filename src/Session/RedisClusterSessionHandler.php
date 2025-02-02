<?php
declare(strict_types=1);
/**
 * This file is part of Heros-Worker.
 * @contact  chenzf@pvc123.com
 */
namespace Framework\Session;

use Framework\Exception\HerosException;

class RedisClusterSessionHandler extends RedisSessionHandler
{
    /**
     * @throws \RedisClusterException
     */
    public function __construct(array $config)
    {
        if (! extension_loaded('redis')) {
            throw new HerosException('please install redis ext');
        }
        parent::__construct($config);
        $this->_maxLifeTime = (int)ini_get('session.gc_maxlifetime');
        $timeout = $config['timeout'] ?? 2;
        $readTimeout = $config['read_timeout'] ?? $timeout;
        $persistent = $config['persistent'] ?? false;
        $auth = $config['auth'] ?? '';
        $args = [null, $config['host'], $timeout, $readTimeout, $persistent];
        if ($auth) {
            $args[] = $auth;
        }
        $this->_redis = new \RedisCluster(...$args);
        if (empty($config['prefix'])) {
            $config['prefix'] = 'redis_session_';
        }
        $this->_redis->setOption(\Redis::OPT_PREFIX, $config['prefix']);
    }
}
