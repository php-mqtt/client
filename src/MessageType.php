<?php

/** @noinspection PhpUnusedPrivateFieldInspection */

declare(strict_types=1);

namespace PhpMqtt\Client;

use MyCLabs\Enum\Enum;

/**
 * An enumeration describing types of messages.
 *
 * @method static MessageType PUBLISH()
 * @method static MessageType PUBLISH_ACKNOWLEDGEMENT()
 * @method static MessageType PUBLISH_RECEIPT()
 * @method static MessageType PUBLISH_RELEASE()
 * @method static MessageType PUBLISH_COMPLETE()
 * @method static MessageType SUBSCRIBE_ACKNOWLEDGEMENT()
 * @method static MessageType UNSUBSCRIBE_ACKNOWLEDGEMENT()
 * @method static MessageType PING_REQUEST()
 * @method static MessageType PING_RESPONSE()
 *
 * @package PhpMqtt\Client
 */
class MessageType extends Enum
{
    private const PUBLISH                     = 'PUBLISH';
    private const PUBLISH_ACKNOWLEDGEMENT     = 'PUBACK';
    private const PUBLISH_RECEIPT             = 'PUBREC';
    private const PUBLISH_RELEASE             = 'PUBREL';
    private const PUBLISH_COMPLETE            = 'PUBCOMP';
    private const SUBSCRIBE_ACKNOWLEDGEMENT   = 'SUBACK';
    private const UNSUBSCRIBE_ACKNOWLEDGEMENT = 'UNSUBACK';
    private const PING_REQUEST                = 'PINGREQ';
    private const PING_RESPONSE               = 'PINGRESP';
}
