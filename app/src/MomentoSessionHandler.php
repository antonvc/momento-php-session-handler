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
    private $found=null;
    private $expiry=null;

    public function __construct()
    {
        $authProvider = CredentialProvider::fromEnvironmentVariable("MOMENTO_AUTH_TOKEN");
        $configuration = Laptop::latest(new StderrLoggerFactory());
        $this->itemDefaultTtlSeconds = getenv('MOMENTO_SESSION_TTL')?:300;
        $this->client = new CacheClient($configuration, $authProvider, $this->itemDefaultTtlSeconds);
        $this->cacheName = getenv('MOMENTO_SESSION_CACHE')?:"php-sessions";

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
        //syslog(LOG_DEBUG, "Opening session $sessionSavePath, $sessionName");
        $this->sessionName = $sessionName;
        return true;
    }

    public function read($sessionId): string
    {
        $response = $this->client->get($this->cacheName, $sessionId);
        if ($hitResponse = $response->asHit()) {
            $data = unserialize($hitResponse->valueString());
            if (isset($data['expiry'])) {
                $this->expiry = $data['expiry'];
                $this->found = true;
                syslog(LOG_DEBUG, "Getting cache cacheName: $this->cacheName, key: $sessionId,  data: " . $data['data'] . " expiry: " . date("H:i:s",$this->expiry));
                return $data['data'];
            }
           
            syslog(LOG_ERR, "Getting cache cacheName: $this->cacheName, key: $sessionId,  Unexpected data: " . $hitResponse->valueString());
            return '';
        } elseif ($response->asError()) {
            $this->found = false;
            syslog(LOG_ERR, "Error reading cache, cacheName: $this->cacheName, key: $sessionId: " . $response->asError()->message());
            return '';
        } else {
            $this->found = false;
            syslog(LOG_DEBUG, "Getting cache cacheName: $this->cacheName, key: $sessionId - not found");

            return '';
        }
    }

    public function write($sessionId, $sessionData): bool
    {
        if ($this->found === true && empty($sessionData)) {
            syslog(LOG_DEBUG, "Deleting session $sessionId");
            $response = $this->client->delete($this->cacheName, $sessionId);
            return $response->asSuccess() !== null;
        }
        if (!empty($sessionData)) {
            $data = serialize(['data' => $sessionData, 'expiry' => time()+ $this->itemDefaultTtlSeconds]);
            syslog(LOG_DEBUG, "Setting cache cacheName: $this->cacheName, key: $sessionId, ttl: $this->itemDefaultTtlSeconds, data:".$data);
            $response = $this->client->set($this->cacheName, $sessionId, $data, $this->itemDefaultTtlSeconds);
            //log error if any...
            if ($response->asError() !== null) {
                syslog(LOG_ERR, "Error set for key: $sessionId:".$response->asError()->message());
            }
            return $response->asSuccess() !== null;
        }
        return true;
    }

    public function validateId($sessionId): bool
    {
        syslog(LOG_DEBUG, "Validating session $sessionId");
        $response = $this->client->get($this->cacheName, $sessionId);
        return $response->asHit() !== null;
    }

    public function updateTimestamp($sessionId, $sessionData): bool
    {
        // //only update if session is over half way to expiry
        // if ($this->expiry !== null && $this->expiry - time() > $this->itemDefaultTtlSeconds/2) {
        //     syslog(LOG_DEBUG, "Skipping session timestamp update $sessionId");
        //     return true;
        // }
        // syslog(LOG_DEBUG, "Updating session timestamp $sessionId");
        // return $this->write($sessionId, $sessionData);
        return true;
    }
}