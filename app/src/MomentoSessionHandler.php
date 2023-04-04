<?php
require "vendor/autoload.php";

use Momento\Auth\CredentialProvider;
use Momento\Cache\CacheClient;
use Momento\Config\Configurations\Laptop;
use Momento\Logging\StderrLoggerFactory;

class MomentoSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private $client;
    private $cacheName;

    public function __construct()
    {
        $authProvider = CredentialProvider::fromEnvironmentVariable("MOMENTO_AUTH_TOKEN");
        $configuration = Laptop::latest(new StderrLoggerFactory());
        $itemDefaultTtlSeconds = 60 * 60; // 1 hour
        $this->client = new CacheClient($configuration, $authProvider, $itemDefaultTtlSeconds);
        $this->cacheName = getenv('MONENTO_SESSION_CACHE')??"php-sessions";
    }

    public function close(): bool
    {
        return true;
    }

    public function destroy($sessionId) :bool
    {
        $response = $this->client->delete($this->cacheName, $sessionId);
        return $response->asSuccess() !== null;
    }

    public function gc($maximumLifetime): int|false
    {
        // Garbage collection is not needed as Momento handles it automatically based on TTL
        return true;
    }

    public function open($sessionSavePath, $sessionName) :bool
    {
        return true;
    }

    public function read($sessionId): string
    {
        $response = $this->client->get($this->cacheName, $sessionId);
        if ($hitResponse = $response->asHit()) {
            return $hitResponse->valueString();
        } else {
            return '';
        }
    }

    public function write($sessionId, $sessionData): bool
    {
        $response = $this->client->set($this->cacheName, $sessionId, $sessionData);
        return $response->asSuccess() !== null;
    }

    public function create_sid()
    {
        return bin2hex(random_bytes(32));
    }

    public function validateId($sessionId): bool
    {
        $response = $this->client->get($this->cacheName, $sessionId);
        return $response->asHit() !== null;
    }

    public function updateTimestamp($sessionId, $sessionData): bool
    {
        return $this->write($sessionId, $sessionData);
    }
}