<?php
/**
 * MomentoSessionHandler
 * 
 * This code is a sample implementation of a session handler using the Momento cache for session storage.
 * It was authored by Anton Van Cauteren (https://github.com/antonvc).
 *
 * Disclaimer: This code is provided as-is and free to use, but without any guarantees or warranties.
 * The author shall not be held liable for any damages or issues caused by the usage of this code. 
 * Please use it at your own risk and ensure you understand its functionality before incorporating it into your project.
 * 
 * This class implements the SessionHandlerInterface and SessionUpdateTimestampHandlerInterface interfaces
 * to provide a session handler that uses Momento cache for session storage.
 * 
 * Momento is a serverless caching service that automatically handles data expiration based on TTL (Time To Live).
 * The class utilizes the Momento PHP SDK to interact with the cache.
 * 
 * It supports storing session data, retrieving session data, updating session data,
 * and deleting sessions. It also handles session data expiration automatically.
 * 
 * Requirements:
 * - Composer package: momentohq/client-sdk-php (should be installed via "composer require momentohq/client-sdk-php")
 * - Momento caching service account with the necessary credentials and authentication token
 *
 * @link https://github.com/momentohq/client-sdk-php Momento PHP SDK
 * @link https://www.gomomento.com Momento Caching Service
 */

require "vendor/autoload.php";

use Momento\Auth\CredentialProvider;
use Momento\Cache\CacheClient;
use Momento\Config\Configurations\Laptop;
use Momento\Logging\StderrLoggerFactory;

class MomentoSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private $client = null;
    private $cacheName;
    private $itemDefaultTtlSeconds;
    private $found = false;
    private $expiry = null;
    private $retries = 0;

    /**
     * MomentoSessionHandler constructor.
     * Initializes the Momento session handler.
     */
    public function __construct()
    {
        // Set the default TTL (Time To Live) for session items in seconds
        $this->itemDefaultTtlSeconds = getenv('MOMENTO_SESSION_TTL') ?: 300;

        // Set the cache name for storing sessions
        $this->cacheName = getenv('MOMENTO_SESSION_CACHE') ?: "php-sessions";

        // Initialize the cache
        $this->initializeCache();
    }

    /**
     * Initializes the Momento cache client.
     *
     * @return bool True if cache initialization was successful, false otherwise.
     */
    private function initializeCache(): bool
    {
        if ($this->retries > 3) {
            syslog(LOG_ERR, "Failed to initialize cache '$this->cacheName' after 3 retries");
            return false;
        }

        // Close any existing client
        if ($this->client !== null) {
            unset($this->client);
        }

        // Get the authentication token from the environment variable
        $authProvider = CredentialProvider::fromEnvironmentVariable("MOMENTO_AUTH_TOKEN");

        // Create the configuration for the cache client
        $configuration = Laptop::latest(new StderrLoggerFactory());

        // Create the cache client
        $this->client = new CacheClient($configuration, $authProvider, $this->itemDefaultTtlSeconds);

        // Set retries so we can abort after too many failures
        $this->retries++;

        return true;
    }

    /**
     * Close the session handler.
     *
     * @return bool Always returns true.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Destroy a session.
     *
     * @param string $sessionId The session ID.
     * @return bool True on successful deletion, false otherwise.
     */
    public function destroy($sessionId): bool
    {
        $response = $this->client->delete($this->cacheName, $sessionId);
        return $response->asSuccess() !== null;
    }

    /**
     * Perform garbage collection.
     *
     * Garbage collection is not needed as Momento handles it automatically based on TTL.
     *
     * @param int $maximumLifetime Maximum session lifetime (not used).
     * @return int|bool Always returns true.
     */
    public function gc($maximumLifetime): int|bool
    {
        return true;
    }

    /**
     * Open the session.
     *
     * @param string $sessionSavePath The session save path (not used).
     * @param string $sessionName The session name (not used).
     * @return bool Always returns true.
     */
    public function open($sessionSavePath, $sessionName): bool
    {
        return true;
    }

    /**
     * Read session data.
     *
     * @param string $sessionId The session ID.
     * @return string The session data as a string.
     */
    public function read($sessionId): string
    {
        // Get the session data from the cache
        $response = $this->client->get($this->cacheName, $sessionId);

        // If the response is a hit, return the data
        if ($hitResponse = $response->asHit()) {
            $data = unserialize($hitResponse->valueString());
            if (isset($data['expiry'])) {
                // Get the expiry time from the session data
                $this->expiry = $data['expiry'];

                // Set the session as found so we can delete it if the data is empty
                $this->found = true;

                // Return the session data
                return $data['data'];
            }
        } elseif ($response->asError()) {
            // If the error is retryable, retry
            if (strpos($response->asError()->message(), "retrying") !== false) {
                if ($this->initializeCache()) {
                    // Initialize cache was successful, retry
                    return $this->read($sessionId);
                }
            }
        }

        // Return empty string if session not found (or failed)
        return '';
    }

    /**
     * Write session data.
     *
     * @param string $sessionId The session ID.
     * @param string $sessionData The session data to be written.
     * @return bool True on successful write operation, false otherwise.
     */
    public function write($sessionId, $sessionData): bool
    {
        // If the session was found and the data is empty, delete the session
        if ($this->found === true && empty($sessionData)) {
            $response = $this->client->delete($this->cacheName, $sessionId);
            return $response->asSuccess() !== null;
        }

        if (!empty($sessionData)) {
            // Add the expiry time to the session data so we can use it to update the timestamp
            $data = serialize(['data' => $sessionData, 'expiry' => time() + $this->itemDefaultTtlSeconds]);
            // Write the session data to the cache
            $response = $this->client->set($this->cacheName, $sessionId, $data, $this->itemDefaultTtlSeconds);

            // Return the status of the write operation
            return $response->asSuccess() !== null;
        }
    }

    /**
     * Validate a session ID.
     *
     * @param string $sessionId The session ID.
     * @return bool True if the session ID exists in the cache, false otherwise.
     */
    public function validateId($sessionId): bool
    {
        $response = $this->client->get($this->cacheName, $sessionId);
        return $response->asHit() !== null;
    }

    /**
     * Update the session timestamp.
     *
     * Only update the timestamp if the session is over halfway to expiry.
     * Otherwise, write the session data to the cache.
     *
     * @param string $sessionId The session ID.
     * @param string $sessionData The session data.
     * @return bool True if the timestamp was updated or the session data was written successfully, false otherwise.
     */
    public function updateTimestamp($sessionId, $sessionData): bool
    {
        // Only update if the session is over halfway to expiry
        if ($this->expiry !== null && $this->expiry - time() > $this->itemDefaultTtlSeconds / 2) {
            return true;
        }

        return $this->write($sessionId, $sessionData);
    }
}