# php-mqtt/client

`php-mqtt/client` was created by, and is maintained by [Namoshek](https://github.com/namoshek). It allows you to connect to an MQTT broker where you can publish messages and subscribe to topics.

## Installation

TODO: requirements
TODO: installation command

## Usage

TODO: examples

## Features

- MQTT Versions
  - [x] v3.1
  - [ ] v3.1.1
  - [ ] v5.0
- Transport
  - [x] TCP (unsecured)
  - [x] TLS (secured, verify peer through certificate authority file)
- Connect
  - [x] Last Will
  - [x] Last Will Topic
  - [x] Last Will Message
  - [x] Required QoS
  - [x] Message Retention
  - [x] Authentication (username & password)
  - [ ] Clean Session (can be set and sent, but the client has no persistence for QoS 2 messages)
- Publish
  - [x] QoS Level 0
  - [x] QoS Level 1 (limitation: no persisted state across sessions)
  - [x] QoS Level 2 (limitation: no persisted state across sessions)
- Subscribe
  - [x] QoS Level 0
  - [x] QoS Level 1
  - [x] QoS Level 2 (limitation: no persisted state across sessions)
- Supported Message Length: unlimited _(no limits enforced, although the MQTT protocol supports only up to 256MB which one shouldn't use even remotely anyway)_
- Logging possible (`Psr\Log\LoggerInterface` can be passed to the client)
- Persistence Drivers
  - [x] In-Memory Driver
  - [ ] Redis Driver

## License

`php-mqtt/client` is an open-sourced software licensed under the [MIT license](LICENSE.md).
