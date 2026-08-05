# Security guidance

- Keep TLS peer and peer-name verification enabled. Supply an explicit CA for private brokers.
- Treat username/password values, authentication data, correlation data, and payloads as sensitive.
- The client logs packet metadata and byte lengths, not raw CONNECT, AUTH, credential, wire, or payload bytes.
- Set a client Maximum Packet Size and Receive Maximum appropriate for the application. Server-advertised
  outbound limits are enforced automatically.
- Use bounded Session Expiry and Message Expiry values so abandoned broker and client state is reclaimed.
- Enhanced-authentication handlers must validate the negotiated method and must not reuse challenge data
  across connections.
- Server Reference is untrusted input. The client exposes redirects but does not automatically connect to a
  different host. Applications which opt in must validate scheme, host, port, TLS policy, and credential
  scope before constructing another client.
- `MemoryRepository` and the supplemental state in `LegacyRepositoryAdapter` are process-local. Use a
  protected persistent `Mqtt5Repository` when QoS/session recovery must survive restarts.
- WebSocket transport is not implemented. Do not infer WebSocket origin, proxy, or TLS protections from the
  TCP/TLS transport.

Report suspected vulnerabilities privately through the repository's GitHub security reporting channel.
