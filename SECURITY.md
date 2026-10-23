# Security Policy

## Supported Versions

Security fixes are targeted at the latest released version, and are also validated against the currently supported PHP versions via CI pipeline.

## Reporting a Vulnerability

Please report security issues **privately** instead of opening a public issue or pull request:

- GitHub security advisories: <https://github.com/php-mqtt/client/security/advisories/new>
- Contact the maintainer: [Marvin Mall](https://github.com/namoshek)

You should receive a response within 3 business days. If the issue is confirmed, a fix is prepared in a private security advisory and released as a patch release.

## Threat Model

The library connects to an MQTT broker that must be treated as an **untrusted peer**: message payloads, topic names, and packet metadata received from the broker are attacker-controlled and are handed to application callbacks and hooks. Applications must validate broker-supplied data before using it (especially before storing or deserializing it).

## Test Fixtures are **NOT** Production Configuration

The TLS material produced by `create-certificates.sh` and the broker configuration in `.ci/mosquitto.conf` are throwaway CI fixtures:

- Key/certificate passphrases are fixed, public values.
- Several listeners run with `allow_anonymous true`, so authentication is not enforced.
- Certificates are self-signed and short-lived.

Never reuse these files or expose the broker ports they describe to the Internet. For real deployments, use certificates from a real CA, enforce authentication on every listener, and keep TLS peer verification enabled.
