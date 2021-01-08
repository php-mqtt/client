#!/bin/sh

openssl genrsa -out .ci/tls/ca.key 2048
openssl req -x509 -new -nodes -key .ci/tls/ca.key -days 1 -out .ci/tls/ca.crt -subj "/C=AT/ST=Vorarlberg/CN=php-mqtt Test CA"
openssl genrsa -out .ci/tls/server.key 2048
openssl req -new -key .ci/tls/server.key -out .ci/tls/server.csr -sha512 -subj "/C=AT/ST=Vorarlberg/CN=emqxt1.php-mqtt-test"
openssl x509 -req -in .ci/tls/server.csr -CA .ci/tls/ca.crt -CAkey .ci/tls/ca.key -CAcreateserial -out .ci/tls/server.crt -days 1 -sha512
openssl genrsa -out .ci/tls/client.key 2048
openssl req -new -key .ci/tls/client.key -out .ci/tls/client.csr -sha512 -subj "/C=AT/ST=Vorarlberg/CN=emqxt1.php-mqtt-test"
openssl x509 -req -in .ci/tls/client.csr -CA .ci/tls/ca.crt -CAkey .ci/tls/ca.key -CAcreateserial -out .ci/tls/client.crt -days 1 -sha256
