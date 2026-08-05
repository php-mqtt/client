# MQTT 5 migration guide

MQTT 5 is additive. Existing constructor arguments, the MQTT 3.1 default, basic methods, and legacy callback
signatures are unchanged.

## Selecting MQTT 5

Pass `MqttClient::MQTT_5_0` as the constructor's fourth argument. Existing `connect`, `publish`, `subscribe`,
`unsubscribe`, and `disconnect` calls then use valid MQTT 5 packets with empty/default properties.

The second `connect` argument is called clean session in the legacy API. With MQTT 5 it is interpreted as
Clean Start. Configure Session Expiry Interval in `Mqtt5\ConnectionOptions` when broker-side session state
must survive a network connection.

## Advanced API

Code which needs MQTT 5 properties or results can depend on `Contracts\Mqtt5Client`. It provides immutable
connection, Will, publish, subscribe, unsubscribe, disconnect, and authentication option objects. It also
provides typed callbacks instead of adding parameters to legacy callbacks.

Properties use `Protocol\Properties`, an ordered immutable collection. Repeated User Properties and incoming
Subscription Identifiers remain distinct and ordered.

## Custom processors

The original `Contracts\MessageProcessor` remains valid for MQTT 3.x processors. MQTT 5 processors implement
the additive `Contracts\Mqtt5MessageProcessor`, delegate wire work to `Protocol\Mqtt5\PacketCodec`, and expose
typed packets through the compatibility `Mqtt5Message`.

Do not extend the mutable legacy `Message` to model additional MQTT 5 fields. Add a typed packet or option
model and adapt it only at the compatibility boundary.

## Custom repositories

The original `Contracts\Repository` remains accepted. For MQTT 5, repositories should implement
`Contracts\Mqtt5Repository` so queued publications, explicit QoS stages, subscriptions, expiry timestamps,
and session metadata can be persisted together.

Legacy repositories are wrapped by `Repositories\LegacyRepositoryAdapter`. The wrapped repository retains
its original persistence behavior, but the adapter's queued-publication index, subscription index, and
session metadata are process-local. Recreating the adapter therefore cannot resume those MQTT 5 details.

`MemoryRepository` implements the MQTT 5 contract and remains the default. It intentionally does not persist
across process restarts.

## Reconnect behavior

MQTT 5 in-flight packets are not retransmitted by an active-connection timer. They are retransmitted only
after a resumed session:

- PUBLISH is retransmitted while awaiting PUBACK or PUBREC.
- PUBREL is retransmitted while awaiting PUBCOMP.
- Expired publications are discarded and Message Expiry Interval is reduced.
- If CONNACK reports no session, known subscriptions are replayed and delivery state is rebuilt.

Connection-scoped topic aliases are cleared for every network connection.

## Callbacks and logging

Legacy callbacks keep their original positional parameters. Register the separate typed MQTT 5 callbacks to
receive properties, reason codes, server DISCONNECT, or AUTH events.

Raw CONNECT, AUTH, credentials, wire bytes, and payload bytes are no longer logged. Custom diagnostics should
log lengths and packet metadata only, unless an application explicitly enables an appropriately redacted
diagnostic sink.
