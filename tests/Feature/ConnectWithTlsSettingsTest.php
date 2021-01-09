<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the client is able to connect to a broker using TLS.
 *
 * @package Tests\Feature
 */
class ConnectWithTlsSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->skipTlsTests) {
            $this->markTestSkipped('TLS tests are disabled.');
        }
    }

    public function test_connecting_with_tls_with_ignored_self_signed_certificate_works_as_intended(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerTlsPort, 'test-tls-settings');

        $connectionSettings = (new ConnectionSettings)
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(true)
            ->setTlsVerifyPeer(false)
            ->setTlsVerifyPeerName(false);

        $client->connect($connectionSettings, true);

        $this->assertTrue($client->isConnected());

        $client->disconnect();
    }

    public function test_connecting_with_tls_with_validated_self_signed_certificate_works_as_intended(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerTlsPort, 'test-tls-settings');

        $connectionSettings = (new ConnectionSettings)
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(false)
            ->setTlsVerifyPeer(true)
            ->setTlsVerifyPeerName(true)
            ->setTlsCertificateAuthorityFile($this->tlsCertificateDirectory . '/ca.crt');

        $client->connect($connectionSettings, true);

        $this->assertTrue($client->isConnected());

        $client->disconnect();
    }

    public function test_connecting_with_tls_and_client_certificate_with_validated_self_signed_certificate_works_as_intended(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerTlsWithClientCertificatePort, 'test-tls-settings');

        $connectionSettings = (new ConnectionSettings)
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(false)
            ->setTlsVerifyPeer(true)
            ->setTlsVerifyPeerName(true)
            ->setTlsCertificateAuthorityFile($this->tlsCertificateDirectory . '/ca.crt')
            ->setTlsClientCertificateFile($this->tlsCertificateDirectory . '/client.crt')
            ->setTlsClientCertificateKeyFile($this->tlsCertificateDirectory . '/client.key');

        $client->connect($connectionSettings, true);

        $this->assertTrue($client->isConnected());

        $client->disconnect();
    }
}
