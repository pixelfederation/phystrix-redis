<?php
declare(strict_types=1);

/*
 * Copyright (c) 2016 PIXEL FEDERATION, s.r.o.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the PIXEL FEDERATION, s.r.o. nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL PIXEL FEDERATION, s.r.o. BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace PixelFederation\Phystrix\Storage;

use Odesk\Phystrix\StateStorageInterface;
use Predis\Client as Redis;

/**
 *
 */
final class RedisStateStorage implements StateStorageInterface
{
    private const BUCKET_EXPIRE_SECONDS = 120;
    private const BUCKET_KEY_FORMAT = 'phystrix_bucket:%s_%s_%s';
    private const CIRCUIT_OPEN_KEY_FORMAT = 'phystrix_circuit_open:%s';
    private const CIRCUIT_TEST_KEY_FORMAT = 'phystrix_circuit_test:%s';

    /**
     * @var Redis
     */
    private $redis;

    /**
     * @param Redis $redis
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @inheritdoc
     */
    public function incrementBucket($commandKey, $type, $index): void
    {
        $key = $this->getBucketKey($commandKey, $type, $index);
        $this->redis->incr($key);
    }

    /**
     * @inheritdoc
     */
    public function getBucket($commandKey, $type, $index)
    {
        $key = $this->getBucketKey($commandKey, $type, $index);
        $currentValue = $this->redis->get($key);

        return $currentValue === false ? null : $currentValue;
    }

    /**
     * @inheritdoc
     */
    public function resetBucket($commandKey, $type, $index): void
    {
        $key = $this->getBucketKey($commandKey, $type, $index);
        if ($this->redis->exists($key)) {
            $this->redis->setex($key, self::BUCKET_EXPIRE_SECONDS, 0);
        }
    }

    /**
     * @inheritdoc
     */
    public function openCircuit($commandKey, $sleepingWindowInMilliseconds): void
    {
        $openKey = $this->getCircuitOpenKey($commandKey);
        $testKey = $this->getCircuitTestKey($commandKey);

        $this->redis->set($openKey, true);

        $sleepingWindowInSeconds = ceil($sleepingWindowInMilliseconds / 1000);
        $this->redis->setex($testKey, $sleepingWindowInSeconds, true);
    }

    /**
     * @inheritdoc
     */
    public function closeCircuit($commandKey): void
    {
        $key = $this->getCircuitOpenKey($commandKey);
        $this->redis->set($key, false);
    }

    /**
     * @inheritdoc
     */
    public function isCircuitOpen($commandKey): bool
    {
        $key = $this->getCircuitOpenKey($commandKey);

        return (bool) $this->redis->get($key);
    }

    /**
     * @inheritdoc
     */
    public function allowSingleTest($commandKey, $sleepingWindowInMilliseconds): bool
    {
        $key = $this->getCircuitTestKey($commandKey);
        $sleepingWindowInSeconds = (int) ceil($sleepingWindowInMilliseconds / 1000);

        if ($this->redis->exists($key)) {
            return false;
        }
        $this->redis->setex($key, $sleepingWindowInSeconds, true);

        return true;
    }

    /**
     * @param string $commandKey
     * @param int $type
     * @param int|string $index
     *
     * @return string
     */
    private function getBucketKey(string $commandKey, int $type, $index): string
    {
        return sprintf(self::BUCKET_KEY_FORMAT, $commandKey, $type, $index);
    }

    /**
     * @param string $commandKey
     *
     * @return string
     */
    private function getCircuitOpenKey(string $commandKey): string
    {
        return sprintf(self::CIRCUIT_OPEN_KEY_FORMAT, $commandKey);
    }

    /**
     * @param string $commandKey
     *
     * @return string
     */
    private function getCircuitTestKey(string $commandKey): string
    {
        return sprintf(self::CIRCUIT_TEST_KEY_FORMAT, $commandKey);
    }
}
