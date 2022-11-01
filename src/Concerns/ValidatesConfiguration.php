<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\MqttClient;

/**
 * Provides methods to validate the configuration of an {@see MqttClient} and
 * the {@see ConnectionSettings} being used to connect to a broker.
 *
 * @package PhpMqtt\Client\Concerns
 */
trait ValidatesConfiguration
{
    /**
     * Ensures the given connection settings are valid. If they are not valid,
     * which means they are misconfigured, an exception containing information about
     * the configuration error is thrown.
     *
     * @param ConnectionSettings $settings
     * @return void
     * @throws ConfigurationInvalidException
     */
    protected function ensureConnectionSettingsAreValid(ConnectionSettings $settings): void
    {
        if ($settings->getConnectTimeout() < 1) {
            throw new ConfigurationInvalidException('The connect timeout cannot be less than 1 second.');
        }

        if ($settings->getSocketTimeout() < 1) {
            throw new ConfigurationInvalidException('The socket timeout cannot be less than 1 second.');
        }

        if ($settings->getResendTimeout() < 1) {
            throw new ConfigurationInvalidException('The resend timeout cannot be less than 1 second.');
        }

        if ($settings->getKeepAliveInterval() < 1 || $settings->getKeepAliveInterval() > 65535) {
            throw new ConfigurationInvalidException('The keep alive interval must be a value in the range of 1 to 65535 seconds.');
        }

        if ($settings->getMaxReconnectAttempts() < 1) {
            throw new ConfigurationInvalidException('The maximum reconnect attempts cannot be fewer than 1.');
        }

        if ($settings->getDelayBetweenReconnectAttempts() < 0) {
            throw new ConfigurationInvalidException('The delay between reconnect attempts cannot be lower than 0.');
        }

        if ($settings->getUsername() !== null && trim($settings->getUsername()) === '') {
            throw new ConfigurationInvalidException('The username may not consist of white space only.');
        }

        if ($settings->getLastWillTopic() !== null && trim($settings->getLastWillTopic()) === '') {
            throw new ConfigurationInvalidException('The last will topic may not consist of white space only.');
        }

        if ($settings->getLastWillQualityOfService() < MqttClient::QOS_AT_MOST_ONCE
            || $settings->getLastWillQualityOfService() > MqttClient::QOS_EXACTLY_ONCE) {
            throw new ConfigurationInvalidException('The QoS for the last will must be a value in the range of 0 to 2.');
        }

        if ($settings->getTlsCertificateAuthorityFile() !== null && !is_file($settings->getTlsCertificateAuthorityFile())) {
            throw new ConfigurationInvalidException('The Certificate Authority file setting must contain the path to a regular file.');
        }

        if ($settings->getTlsCertificateAuthorityPath() !== null && !is_dir($settings->getTlsCertificateAuthorityPath())) {
            throw new ConfigurationInvalidException('The Certificate Authority path setting must contain the path to a directory.');
        }

        if ($settings->getTlsClientCertificateFile() !== null && !is_file($settings->getTlsClientCertificateFile())) {
            throw new ConfigurationInvalidException('The client certificate file setting must contain the path to a regular file.');
        }

        if ($settings->getTlsClientCertificateKeyFile() !== null && !is_file($settings->getTlsClientCertificateKeyFile())) {
            throw new ConfigurationInvalidException('The client certificate key file setting must contain the path to a regular file.');
        }

        if ($settings->getTlsClientCertificateKeyFile() !== null && $settings->getTlsClientCertificateFile() === null) {
            throw new ConfigurationInvalidException('Using a client certificate key file without certificate does not work.');
        }

        if ($settings->getTlsClientCertificateKeyPassphrase() !== null && $settings->getTlsClientCertificateKeyFile() === null) {
            throw new ConfigurationInvalidException('Using a client certificate key passphrase without key file does not work.');
        }
    }
}
