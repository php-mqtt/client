<?php declare(strict_types=1);

namespace PhpMqtt\Protocol;

class ProtocolException
        extends \Exception
{

}

class DataEncoder
{

  public static function uint16(int $value): string
  {
    return pack("n", $value);
  }

  public static function utf8string(string $value): string
  {
    $length = strlen($value);
    if ($length > 65535)
      throw new ProtocolException("String too long ($length, max 65535)");

    return pack("n", $length) . $value;
  }

  public static function varint($value): string
  {
    $retval = "";
    do {
      $byte = $value % 128;
      $value >>= 7;
      if ($value > 0)
        $byte |= 0x80;

      $retval .= chr($byte);
    } while ($value > 0);

    return $retval;
  }
}
