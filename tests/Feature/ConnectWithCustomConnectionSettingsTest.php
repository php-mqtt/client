<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the client is able to connect to a broker with custom connection settings.
 *
 * @package Tests\Feature
 */
class ConnectWithCustomConnectionSettingsTest extends TestCase
{
    public function test_connecting_with_custom_connection_settings_works_as_intended(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-custom-connection-settings');

        $connectionSettings = (new ConnectionSettings)
            ->setLastWillTopic('foo/last/will')
            ->setLastWillMessage('baz is out!')
            ->setLastWillQualityOfService(MqttClient::QOS_AT_MOST_ONCE)
            ->setRetainLastWill(true)
            ->setConnectTimeout(3)
            ->setSocketTimeout(3)
            ->setResendTimeout(3)
            ->setKeepAliveInterval(30)
            ->setUsername(null)
            ->setPassword(null)
            ->setUseTls(false)
            ->setTlsCertificateAuthorityFile(null)
            ->setTlsCertificateAuthorityPath(null)
            ->setTlsClientCertificateFile(null)
            ->setTlsClientCertificateKeyFile(null)
            ->setTlsClientCertificateKeyPassphrase(null)
            ->setTlsVerifyPeer(false)
            ->setTlsVerifyPeerName(false)
            ->setTlsSelfSignedAllowed(true);

        $client->connect($connectionSettings);

        $this->assertTrue($client->isConnected());

        $client->disconnect();
    }
}
