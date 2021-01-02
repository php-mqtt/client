<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client encountered an error while transferring data.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class DataTransferException extends MqttClientException
{
    const EXCEPTION_TX_DATA = 0101;
    const EXCEPTION_RX_DATA = 0102;

    /**
     * DataTransferException constructor.
     *
     * @param int    $code
     * @param string $error
     */
    public function __construct(int $code, string $error)
    {
        parent::__construct(
            sprintf('[%s] Transferring data over socket failed: %s', $code, $error),
            $code
        );
    }
}
