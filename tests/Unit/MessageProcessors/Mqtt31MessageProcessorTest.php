<?php

declare(strict_types=1);

namespace Tests\Unit\MessageProcessors;

use PhpMqtt\Client\Logger;
use PhpMqtt\Client\MessageProcessors\Mqtt31MessageProcessor;
use PHPUnit\Framework\TestCase;

class Mqtt31MessageProcessorTest extends TestCase
{
    /** @var Mqtt31MessageProcessor */
    protected $messageProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageProcessor = new Mqtt31MessageProcessor('test-client', new Logger('test.local', 1883, 'test-client'));
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
    )
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
}
