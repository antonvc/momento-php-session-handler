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
    private $itemDefaultTtlSeconds;
    private $debug;

    public function __construct()
    {
        $authProvider = CredentialProvider::fromEnvironmentVariable("MOMENTO_AUTH_TOKEN");
        $configuration = Laptop::latest(new StderrLoggerFactory());
        $this->itemDefaultTtlSeconds = getenv('MONENTO_SESSION_CACHE_TTL')?:300;
        $this->client = new CacheClient($configuration, $authProvider, $this->itemDefaultTtlSeconds);
        $this->cacheName = getenv('MONENTO_SESSION_CACHE')?:"php-sessions";

        //make sure system logger is going to stdout
        openlog("php", LOG_PID | LOG_PERROR, LOG_LOCAL0);


        syslog(LOG_DEBUG, "Using cache $this->cacheName with TTL $this->itemDefaultTtlSeconds");
    }

    public function close(): bool
    {
        syslog(LOG_DEBUG, "Closing session");
        return true;
    }

    public function destroy($sessionId) :bool
    {
        syslog(LOG_DEBUG, "Destroying session $sessionId");
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
        syslog(LOG_DEBUG, "Opening session $sessionSavePath, $sessionName");
        return true;
    }

    public function read($sessionId): string
    {
        syslog(LOG_DEBUG, "Reading session $sessionId");
        $response = $this->client->get($this->cacheName, $sessionId);
        if ($hitResponse = $response->asHit()) {
            return $hitResponse->valueString();
        } else {
            return '';
        }
    }

    public function write($sessionId, $sessionData): bool
    {
        syslog(LOG_DEBUG, "Writing session $sessionId");
        $response = $this->client->set($this->cacheName, $sessionId, $sessionData, $this->itemDefaultTtlSeconds);
        return $response->asSuccess() !== null;
    }

    public function validateId($sessionId): bool
    {
        syslog(LOG_DEBUG, "Validating session $sessionId");
        $response = $this->client->get($this->cacheName, $sessionId);
        return $response->asHit() !== null;
    }

    public function updateTimestamp($sessionId, $sessionData): bool
    {
        syslog(LOG_DEBUG, "Updating session timestampo $sessionId");
        return $this->write($sessionId, $sessionData);
    }
}