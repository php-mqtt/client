<?php

declare(strict_types=1);

namespace Tests\Unit\MessageProcessors;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Logger;
use PhpMqtt\Client\MessageProcessors\Mqtt31MessageProcessor;
use PHPUnit\Framework\TestCase;

class Mqtt31MessageProcessorTest extends TestCase
{
    const CLIENT_ID = 'test-client';

    /** @var Mqtt31MessageProcessor */
    protected $messageProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageProcessor = new Mqtt31MessageProcessor('test-client', new Logger('test.local', 1883, self::CLIENT_ID));
    }

    public function tryFindMessageInBuffer_testDataProvider(): array
    {
        return [
            // No message and/or no knowledge about the remaining length of the message.
            [hex2bin(''), false, null, null],
            [hex2bin('20'), false, null, null],

            // Incomplete message with knowledge about the remaining length of the message.
            [hex2bin('2002'), false, null, 4],
            [hex2bin('200200'), false, null, 4],

            // Buffer contains only one complete message.
            [hex2bin('20020000'), true, hex2bin('20020000'), null],
            [hex2bin('800a0a03612f6201632f6402'), true, hex2bin('800a0a03612f6201632f6402'), null],

            // Buffer contains more than one complete message.
            [hex2bin('2002000044'), true, hex2bin('20020000'), null],
            [hex2bin('4002000044'), true, hex2bin('40020000'), null],
            [hex2bin('400200004412345678'), true, hex2bin('40020000'), null],
        ];
    }

    /**
     * @dataProvider tryFindMessageInBuffer_testDataProvider
     *
     * @param string      $buffer
     * @param bool        $expectedResult
     * @param string|null $expectedMessage
     * @param int|null    $expectedRequiredBytes
     */
    public function test_tryFindMessageInBuffer_finds_messages_correctly(
        string $buffer,
        bool $expectedResult,
        ?string $expectedMessage,
        ?int $expectedRequiredBytes
    ): void
    {
        $message       = null;
        $requiredBytes = -1;

        $result = $this->messageProcessor->tryFindMessageInBuffer($buffer, strlen($buffer), $message, $requiredBytes);

        $this->assertEquals($expectedResult, $result);
        $this->assertEquals($expectedMessage, $message);
        if ($expectedRequiredBytes !== null) {
            $this->assertEquals($expectedRequiredBytes, $requiredBytes);
        } else {
            $this->assertEquals(-1, $requiredBytes);
        }
    }

    public function buildConnectMessage_testDataProvider(): array
    {
        return [
            // Default parameters
            [new ConnectionSettings(), false, hex2bin('101900064d51497364700300000a000b') . self::CLIENT_ID],

            // Clean Session
            [new ConnectionSettings(), true, hex2bin('101900064d51497364700302000a000b') . self::CLIENT_ID],

            // Username, Password and Clean Session
            [
                (new ConnectionSettings())
                    ->setUsername('foo')
                    ->setPassword('bar'),
                true,
                hex2bin('102300064d514973647003c2000a000b') . self::CLIENT_ID . hex2bin('0003') . 'foo' . hex2bin('0003') . 'bar',
            ],

            // Last Will Topic, Last Will Message and Clean Session
            [
                (new ConnectionSettings())
                    ->setLastWillTopic('test/foo')
                    ->setLastWillMessage('bar')
                    ->setLastWillQualityOfService(1),
                true,
                hex2bin('102800064d5149736470030e000a000b') . self::CLIENT_ID . hex2bin('0008') . 'test/foo' . hex2bin('0003') . 'bar',
            ],

            // Last Will Topic, Last Will Message, Retain Last Will, Username, Password and Clean Session
            [
                (new ConnectionSettings())
                    ->setLastWillTopic('test/foo')
                    ->setLastWillMessage('bar')
                    ->setLastWillQualityOfService(2)
                    ->setRetainLastWill(true)
                    ->setUsername('blub')
                    ->setPassword('blubber'),
                true,
                hex2bin('103700064d514973647003f6000a000b') . self::CLIENT_ID . hex2bin('0008') . 'test/foo' . hex2bin('0003') . 'bar'
                    . hex2bin('0004') . 'blub' . hex2bin('0007') . 'blubber',
            ],
        ];
    }

    /**
     * @dataProvider buildConnectMessage_testDataProvider
     *
     * @param ConnectionSettings $connectionSettings
     * @param bool               $useCleanSession
     * @param string             $expectedResult
     */
    public function test_buildConnectMessage_builds_correct_message(
        ConnectionSettings $connectionSettings,
        bool $useCleanSession,
        string $expectedResult
    ): void
    {
        $result = $this->messageProcessor->buildConnectMessage($connectionSettings, $useCleanSession);

        $this->assertEquals($expectedResult, $result);
    }
}
