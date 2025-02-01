<?php
namespace App\Service;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Predis\Client;

class RedisService
{
    private Client $redis;

    public function __construct()
    {
        $this->redis = new Client(['scheme' => 'tcp', 'host' => 'localhost', 'port' => 6379]);
    }

    public function getRedis(): Client
    {
        return $this->redis;
    }
}