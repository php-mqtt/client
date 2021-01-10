#!/bin/sh

VERSION=$1
PORTS=$2
CERTIFICATES=$3
CONFIG=$4
PLUGINS=$5
CONTAINERNAME=$6

echo "Certificates: $CERTIFICATES"
echo "Config: $CONFIG"

docker_run="docker run --detach --name $CONTAINERNAME"

for i in $(echo $PORTS | tr " " "\n")
do
  docker_run="$docker_run --publish $i"
done

if [ -n "$CERTIFICATES" ]; then
  docker_run="$docker_run --volume $CERTIFICATES:/rabbitmq-certs:ro"
fi

if [ -n "$CONFIG" ]; then
  docker_run="$docker_run --volume $CONFIG:/etc/rabbitmq/rabbitmq.conf:ro"
fi

docker_run="$docker_run rabbitmq:$VERSION"

if [ -n "$PLUGINS" ]; then
  PLUGINCONTENT="'[$PLUGINS].'"
  docker_run="$docker_run sh -c \"echo $PLUGINCONTENT > /etc/rabbitmq/enabled_plugins && rabbitmq-server\""
fi

echo "$docker_run"
sh -c "$docker_run"
