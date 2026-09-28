<?php

/**
 * Encrypted Vehicle for MODX Revolution 3.x
 *
 * Encrypts package contents during build and decrypts during installation.
 * Requires valid license from modstore.pro to install.
 *
 * @package msp3deliveryskeleton
 * @subpackage Transport
 *
 * @see https://modstore.pro/info/api
 */

namespace Ibochkarev\Msp3DeliverySkeleton\Transport;

use MODX\Revolution\Transport\modTransportPackage;
use MODX\Revolution\Transport\modTransportProvider;
use xPDO\Transport\xPDOObjectVehicle;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

class EncryptedVehicle extends xPDOObjectVehicle
{
    /**
     * Vehicle class identifier
     *
     * @var string
     */
    public $class = 'Ibochkarev\\Msp3DeliverySkeleton\\Transport\\EncryptedVehicle';

    /**
     * Vehicle version for API compatibility
     */
    public const VERSION = '3.0.0';

    /**
     * Encryption cipher
     */
    public const CIPHER = 'AES-256-CBC';

    /**
     * License server URL
     */
    public const LICENSE_SERVER = 'https://modstore.pro/extras/';

    /**
     * Put object into transport package with encryption
     *
     * @param xPDOTransport $transport
     * @param mixed $object
     * @param array $attributes
     */
    public function put(&$transport, &$object, $attributes = [])
    {
        parent::put($transport, $object, $attributes);

        /** @var modTransportProvider $provider */
        $provider = $transport->xpdo->getObject(modTransportProvider::class, [
            'service_url' => self::LICENSE_SERVER,
        ]);
        if (!$provider) {
            $provider = $transport->xpdo->getObject(modTransportProvider::class, ['name' => 'modstore.pro']);
        }

        if (!$provider) {
            throw new \Exception('Provider not found for: ' . self::LICENSE_SERVER);
        }

        $key = $this->getKey($transport, 'encode', $provider);

        if (!$key) {
            throw new \Exception('Failed to get encryption key from license server.');
        }

        $this->payload['object_encrypted'] = $this->encode(
            $this->payload['object'],
            $key
        );
        unset($this->payload['object']);

        if (isset($this->payload['related_objects'])) {
            $this->payload['related_objects_encrypted'] = $this->encode(
                $this->payload['related_objects'],
                $key
            );
            unset($this->payload['related_objects']);
        }

        $transport->xpdo->log(xPDO::LOG_LEVEL_INFO, 'Package encrypted!');
    }

    /**
     * Install encrypted vehicle
     *
     * @param xPDOTransport $transport
     * @param array $options
     * @return bool
     */
    public function install(&$transport, $options)
    {
        if (!$this->decodePayloads($transport, 'install')) {
            return false;
        }

        return parent::install($transport, $options);
    }

    /**
     * Uninstall encrypted vehicle
     *
     * @param xPDOTransport $transport
     * @param array $options
     * @return bool
     */
    public function uninstall(&$transport, $options)
    {
        if (!$this->decodePayloads($transport, 'uninstall')) {
            return false;
        }

        return parent::uninstall($transport, $options);
    }

    /**
     * Encode data with AES-256-CBC
     *
     * @param array $data Data to encrypt
     * @param string $key Encryption key
     * @return string Base64 encoded encrypted data
     */
    protected function encode($data, $key)
    {
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLen);

        $cipherRaw = openssl_encrypt(
            serialize($data),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($iv . $cipherRaw);
    }

    /**
     * Decode data with AES-256-CBC
     *
     * @param string $string Base64 encoded encrypted data
     * @param string $key Decryption key
     * @return mixed Decrypted data or false on failure
     */
    protected function decode($string, $key)
    {
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $encoded = base64_decode($string);

        $iv = substr($encoded, 0, $ivLen);
        $cipherRaw = substr($encoded, $ivLen);

        $decrypted = openssl_decrypt(
            $cipherRaw,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            return false;
        }

        return unserialize($decrypted);
    }

    /**
     * Decode encrypted payloads
     *
     * @param xPDOTransport $transport
     * @param string $action install|uninstall
     * @return bool
     */
    protected function decodePayloads(&$transport, $action = 'install')
    {
        $hasEncrypted = isset($this->payload['object_encrypted'])
            || isset($this->payload['related_objects_encrypted']);

        if (!$hasEncrypted) {
            return true;
        }

        $key = $this->getKey($transport, $action);

        if (!$key) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Failed to get decryption key. Installation aborted.'
            );
            return false;
        }

        if (isset($this->payload['object_encrypted'])) {
            $decrypted = $this->decode($this->payload['object_encrypted'], $key);

            if ($decrypted === false) {
                $transport->xpdo->log(
                    xPDO::LOG_LEVEL_ERROR,
                    'Failed to decrypt package. Invalid key or corrupted data.'
                );
                return false;
            }

            $this->payload['object'] = $decrypted;
            unset($this->payload['object_encrypted']);
        }

        if (isset($this->payload['related_objects_encrypted'])) {
            $decrypted = $this->decode($this->payload['related_objects_encrypted'], $key);

            if ($decrypted === false) {
                $transport->xpdo->log(
                    xPDO::LOG_LEVEL_ERROR,
                    'Failed to decrypt related objects. Invalid key or corrupted data.'
                );
                return false;
            }

            $this->payload['related_objects'] = $decrypted;
            unset($this->payload['related_objects_encrypted']);
        }

        $transport->xpdo->log(xPDO::LOG_LEVEL_INFO, 'Package decrypted!');

        return true;
    }

    /**
     * Get encryption/decryption key from modstore.pro
     *
     * @param xPDOTransport $transport
     * @param string $action encode|install|uninstall
     * @param modTransportProvider|null $provider
     * @return string|false
     */
    protected function getKey(&$transport, $action, $provider = null)
    {
        $key = false;

        $endpoint = $action === 'encode'
            ? 'package/encode'
            : 'package/decode/' . $action;

        $package = null;

        if (!$provider) {
            /** @var modTransportPackage $package */
            $package = $transport->xpdo->getObject(modTransportPackage::class, [
                'signature' => $transport->signature,
            ]);

            if (!($package instanceof modTransportPackage)) {
                $transport->xpdo->log(
                    xPDO::LOG_LEVEL_ERROR,
                    'Transport package not found: ' . $transport->signature
                );
                return false;
            }

            /** @var modTransportProvider $provider */
            $provider = $package->getOne('Provider');

            if (!$provider) {
                $provider = $transport->xpdo->getObject(modTransportProvider::class, [
                    'service_url' => self::LICENSE_SERVER,
                ]);
            }
        }

        if (!$provider) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Package provider not found. Please ensure modstore.pro is configured as provider.'
            );
            return false;
        }

        $provider->xpdo->setOption('contentType', 'default');

        $packageName = $package
            ? $package->package_name
            : explode('-', $transport->signature)[0];

        $signatureParts = explode('-', $transport->signature);
        $version = ($signatureParts[1] ?? '')
            . (isset($signatureParts[2]) ? '-' . $signatureParts[2] : '');

        $params = [
            'package' => $packageName,
            'version' => $version,
            'username' => $provider->username,
            'api_key' => $provider->api_key,
            'vehicle_version' => self::VERSION,
        ];

        $response = $provider->request($endpoint, 'POST', $params);

        if ($response === false) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Failed to connect to license server'
            );
            return false;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $msg = 'License API error: HTTP ' . $statusCode;
            if ($statusCode === 404 && $action === 'encode') {
                $msg .= '. Package/signature is not registered for modstore encode, or provider credentials are wrong. '
                    . 'For local builds set encrypt => false in _build/config.inc.php.';
            }
            $transport->xpdo->log(xPDO::LOG_LEVEL_ERROR, $msg);
            return false;
        }

        $body = (string) $response->getBody();

        if (empty($body)) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Empty response from license server'
            );
            return false;
        }

        try {
            $xml = @simplexml_load_string($body);
        } catch (\Exception $e) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Could not parse XML response from provider: ' . $body
            );
            return false;
        }

        if ($xml === false) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Invalid XML response from license server'
            );
            return false;
        }

        if ($xml->getName() === 'error') {
            $msg = !empty($xml->message) ? (string) $xml->message : 'Unknown error';
            $transport->xpdo->log(xPDO::LOG_LEVEL_ERROR, 'License API error: ' . $msg);
            return false;
        }

        if (!empty($xml->key)) {
            $key = (string) $xml->key;
        } elseif (!empty($xml->message)) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'License error: ' . (string) $xml->message
            );
        } else {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Invalid response from license server'
            );
        }

        return $key;
    }
}
