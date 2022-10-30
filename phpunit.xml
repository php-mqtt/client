<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         enforceTimeLimit="true"
         defaultTimeLimit="3"
         timeoutForSmallTests="2"
         timeoutForMediumTests="5"
         timeoutForLargeTests="10"
>
    <php>
        <env name="MQTT_BROKER_HOST" value="localhost"/>
        <env name="MQTT_BROKER_PORT" value="1883"/>
        <env name="MQTT_BROKER_PORT_WITH_AUTHENTICATION" value="1884"/>
        <env name="MQTT_BROKER_TLS_PORT" value="8883"/>
        <env name="MQTT_BROKER_TLS_WITH_CLIENT_CERT_PORT" value="8884"/>
        <env name="TLS_CERT_DIR" value=".ci/tls"/>
        <env name="SKIP_TLS_TESTS" value="false"/>
    </php>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">tests/Feature</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
