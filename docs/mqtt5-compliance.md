# MQTT 5.0 compliance matrix

This matrix is the release gate for the additive MQTT 5 implementation. Its normative source is the
[OASIS MQTT Version 5.0 specification](https://docs.oasis-open.org/mqtt/mqtt/v5.0/os/mqtt-v5.0-os.html).
Requirement identifiers below are the specification's identifiers; a comma-separated range is used only
when every identifier in that range has the same implementation and verification.

Status meanings:

- **Verified**: covered by a deterministic automated test.
- **Integration**: covered by the Mosquitto feature suite and scheduled conformance jobs.
- **Conditional**: outside the current transport surface and must not be advertised.

## Data representation and framing

| Requirement identifiers | Requirement | Implementation | Verification | Status |
| --- | --- | --- | --- | --- |
| MQTT-1.5.2-1, MQTT-1.5.2-2 | Two-byte and four-byte integers use network byte order. | `Protocol\Wire\BinaryReader`, `BinaryWriter` | `BinaryPrimitivesTest::test_writer_and_reader_round_trip_all_wire_types` | Verified |
| MQTT-1.5.3-1 | Binary Data is prefixed by a two-byte length and cannot be truncated. | `BinaryReader::readBinaryData`, `BinaryWriter::writeBinaryData` | `BinaryPrimitivesTest::test_reader_rejects_truncated_binary_data` | Verified |
| MQTT-1.5.4-1, MQTT-1.5.4-2, MQTT-1.5.4-3, MQTT-1.5.4-4, MQTT-1.5.4-5 | UTF-8 data is length-prefixed, well formed, and excludes null, surrogate, and non-character code points. | `Utf8Validator`, UTF-8 reader/writer methods | `BinaryPrimitivesTest::test_writer_rejects_prohibited_utf8`; malformed codec tests | Verified |
| MQTT-1.5.5-1, MQTT-1.5.5-2 | Variable Byte Integers are canonical, at most four bytes, and no greater than 268,435,455. | `BinaryReader::readVariableByteInteger`, `BinaryWriter::writeVariableByteInteger` | `BinaryPrimitivesTest::test_reader_rejects_malformed_variable_byte_integers` | Verified |
| MQTT-1.5.6-1 | UTF-8 pairs contain two independently validated strings. | `BinaryReader::readUtf8Pair`, `BinaryWriter::writeUtf8Pair` | Property round-trip tests | Verified |
| MQTT-2.1.2-1, MQTT-2.2.1-1 | Control packet type and fixed-header flags are validated. | `PacketType`, `PacketCodec::validateFixedHeaderFlags` | `PacketCodecTest::test_malformed_packets_are_rejected` | Verified |
| MQTT-2.2.2-1, MQTT-2.2.3-1, MQTT-2.2.3-2 | Remaining Length is canonical and matches the packet body. | `PacketFramer`, `PacketCodec::decode` | `PacketFramerTest`; malformed packet vectors | Verified |
| MQTT-2.2.3-3, MQTT-2.2.3-4 | Protocol and configured packet limits are enforced before buffering a complete packet. | `PacketFramer`, negotiated `PacketCodec` limit | `PacketFramerTest::test_framer_enforces_configured_limit_from_the_header` | Verified |
| MQTT-2.3.1-1, MQTT-2.3.1-2 | Required packet identifiers are non-zero and unique while in use. | Typed packet constructors; `Repository::newMessageId` | Packet codec and repository tests | Verified |
| MQTT-2.4.2-1 | Property identifiers, packet availability, multiplicity, and values are validated. | `PropertyCodec` | `PropertyCodecTest` (all property types and negative cases) | Verified |

## Control packets

| Requirement identifiers | Packet / behavior | Implementation | Verification | Status |
| --- | --- | --- | --- | --- |
| MQTT-3.1.1-1, MQTT-3.1.2-1 through MQTT-3.1.2-11, MQTT-3.1.3-1 through MQTT-3.1.3-12 | CONNECT protocol name/level, flags, Clean Start, keep alive, properties, client ID, Will, username, and binary password. | `ConnectPacket`, `Will`, `PacketCodec::encodeConnect/decodeConnect` | CONNECT golden vector; property groups; `Mqtt5MessageProcessorTest` | Verified |
| MQTT-3.2.1-1, MQTT-3.2.2-1 through MQTT-3.2.2-13 | CONNACK flags, reason whitelist, Session Present, assigned ID, capabilities, authentication, and limits. | `ConnAckPacket`, `ConnectionResult`, `NegotiatedCapabilities` | CONNACK golden vector and processor handshake test | Verified |
| MQTT-3.3.1-1 through MQTT-3.3.1-4, MQTT-3.3.2-1 through MQTT-3.3.2-16, MQTT-3.3.3-1 | PUBLISH flags, topic/alias, packet ID, payload, expiry, request/response metadata, subscription identifiers, and user properties. | `PublishPacket`, `PropertyCodec`, alias maps | PUBLISH golden/property vectors; `Mqtt5PublishSubscribeTest` | Integration |
| MQTT-3.4.1-1, MQTT-3.4.2-1 through MQTT-3.4.2-3 | PUBACK compact/full forms, reasons, and properties. | `AcknowledgementPacket`, codec acknowledgement methods | PUBACK vectors | Verified |
| MQTT-3.5.1-1, MQTT-3.5.2-1 through MQTT-3.5.2-3 | PUBREC compact/full forms, reasons, and properties. | `AcknowledgementPacket`, codec acknowledgement methods | PUBREC vectors and QoS stage tests | Verified |
| MQTT-3.6.1-1, MQTT-3.6.2-1 through MQTT-3.6.2-3 | PUBREL flags, packet ID, reason, properties, and retransmission stage. | `AcknowledgementPacket`, `FlowStage` | PUBREL vector; `PublishedMessageStateTest` | Verified |
| MQTT-3.7.1-1, MQTT-3.7.2-1 through MQTT-3.7.2-3 | PUBCOMP compact/full forms and completion. | `AcknowledgementPacket`, operation results | PUBCOMP vector; state tests | Verified |
| MQTT-3.8.1-1, MQTT-3.8.2-1 through MQTT-3.8.2-6, MQTT-3.8.3-1 through MQTT-3.8.3-4 | Batched SUBSCRIBE, Subscription Identifier, User Properties, and per-filter options. | `SubscribePacket`, `SubscriptionRequest`, public subscription options | SUBSCRIBE vector and property tests | Verified |
| MQTT-3.9.1-1, MQTT-3.9.2-1 through MQTT-3.9.3-2 | SUBACK per-filter reason codes preserve successful filters. | `ResultPacket`, `MqttClient::handleMessage` | SUBACK vector and integration flow | Integration |
| MQTT-3.10.1-1, MQTT-3.10.2-1 through MQTT-3.10.3-2 | Batched UNSUBSCRIBE and User Properties. | `UnsubscribePacket`, `UnsubscribeOptions` | UNSUBSCRIBE vector | Verified |
| MQTT-3.11.1-1, MQTT-3.11.2-1 through MQTT-3.11.3-2 | UNSUBACK per-filter reason codes. | `ResultPacket`, `MqttClient::handleMessage` | UNSUBACK vector | Verified |
| MQTT-3.12.1-1, MQTT-3.13.1-1 | PINGREQ/PINGRESP have zero Remaining Length and correct flags. | `EmptyPacket`, keep-alive handling | PING vectors and malformed vectors | Verified |
| MQTT-3.14.1-1, MQTT-3.14.2-1 through MQTT-3.14.2-6 | Compact/full DISCONNECT, reason whitelist, expiry constraint, Server Reference, and properties. | `DisconnectPacket`, `DisconnectOptions`, server event | Compact DISCONNECT vectors and option validation | Verified |
| MQTT-3.15.1-1, MQTT-3.15.2-1 through MQTT-3.15.2-7 | Compact/full AUTH, reason whitelist, method/data consistency, and properties. | `AuthPacket`, `AuthenticationOptions`, `AuthenticationHandler` | AUTH vector and enhanced-authentication challenge test | Verified |

## Sessions, delivery, subscriptions, and flow control

| Requirement identifiers | Requirement | Implementation | Verification | Status |
| --- | --- | --- | --- | --- |
| MQTT-4.1.0-1 through MQTT-4.1.0-3 | Session state is reset on Clean Start and resumed only when CONNACK has Session Present. | `MqttClient::connectConfigured`, reconnect handlers, MQTT 5 repository | state and broker integration tests | Integration |
| MQTT-4.3.1-1, MQTT-4.3.2-1, MQTT-4.3.3-1 through MQTT-4.3.3-11 | QoS 0/1/2 flows and duplicates follow explicit stages. | `FlowStage`, `PublishedMessage`, client packet handling | `PublishedMessageStateTest`; MQTT 3 regression tests | Verified |
| MQTT-4.4.0-1, MQTT-4.4.0-2 | MQTT 5 retransmission occurs only when a session is resumed; PUBREL is retransmitted after PUBREC. | `resumeMqtt5Session`; MQTT 5 timer resend suppression | QoS stage tests | Verified |
| MQTT-4.5.0-1, MQTT-4.6.0-1 through MQTT-4.6.0-6 | Delivery ordering and packet identifier reuse are preserved by repository insertion order. | MQTT 5 repository and flow queue | repository tests | Verified |
| MQTT-4.7.1-1 through MQTT-4.7.3-4 | Topic names, filters, wildcard placement, shared subscriptions, and the `$` topic rule are enforced. | `Topic`, `Subscription` | `TopicTest` | Verified |
| MQTT-4.8.2-1 | Subscription options and negotiated wildcard/shared/identifier capabilities are checked before transmission. | `MqttClient::subscribeWithOptions` | option/codec tests | Verified |
| MQTT-4.9.0-1, MQTT-4.9.0-2 | Server and client Receive Maximum limits are enforced; excess outbound messages are queued. | MQTT 5 queue and inbound counter | repository/state tests | Verified |
| MQTT-4.10.0-1 through MQTT-4.10.0-4 | Message Expiry is reduced on retransmission and expired queued messages are discarded. | `PublishedMessage` expiry timestamp; reconnect/queue handlers | `PublishedMessageStateTest` | Verified |
| MQTT-4.12.0-1 through MQTT-4.12.0-5 | Initial authentication, challenge/response, and reauthentication use one method. | authentication options/handler and handshake loop | enhanced-authentication challenge test | Verified |
| MQTT-4.13.0-1, MQTT-4.13.0-2 | Malformed Packet and Protocol Error are distinguished and cause a permitted DISCONNECT before close. | distinct exceptions; `sendMqtt5Disconnect` | malformed and property negative tests | Verified |

## Request/response, redirection, and security

| Requirement identifiers / section | Requirement | Implementation / policy | Verification | Status |
| --- | --- | --- | --- | --- |
| MQTT-3.3.2-13 through MQTT-3.3.2-16, §4.10 | Response Topic, Correlation Data, Response Information, and request metadata remain available to callers. | ordered properties and `IncomingPublication` | all-property round trip | Verified |
| MQTT-3.2.2-9, MQTT-3.14.2-5, §4.11 | Redirect reasons and Server Reference are exposed; no cross-host connection is automatic. | `ConnectionResult`, `ServerDisconnect`; opt-in option reserved | DISCONNECT/CONNACK property tests | Verified |
| §5.3, §5.4.1, §5.4.8, §5.4.9 | TLS peer verification remains the default; credentials, CONNECT/AUTH, wire bytes, and payload bytes are not logged. | `ConnectionSettings`; redacted client logging; `docs/security.md` | source review and existing TLS feature tests | Integration |
| §5.4.10, §5.4.11 | Packet, receive, and rate-related resource limits are bounded. | packet-size/Receive Maximum enforcement | framing and queue tests | Verified |
| §6 | MQTT over WebSocket rules apply only when a WebSocket transport exists. No transport is implemented or advertised. | README feature table | documentation review | Conditional |

## Release gate

A release candidate must satisfy all of the following:

1. `composer test:cs` and `composer test:unit` pass, including all negative vectors.
2. Existing MQTT 3.1 and 3.1.1 byte-vector and feature suites pass unchanged.
3. Mosquitto integration passes for MQTT 3.1, 3.1.1, and 5.0.
4. The pinned Paho conformance job and scheduled Mosquitto/EMQX/HiveMQ matrix pass.
5. No matrix row marked Verified or Integration is failing or skipped on its designated release job.
6. Security review confirms no raw credentials or packet/payload bytes are logged.
7. WebSocket support remains unadvertised until its conditional rows have an implementation and tests.
