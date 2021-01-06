#!/bin/sh

docker_run="docker run --detach --name mosquitto"

for i in $(echo $INPUT_PORTS | tr " " "\n")
do
  docker_run="$docker_run --publish $i"
done

if [ -n "$INPUT_CERTIFICATES" ]; then
  docker_run="$docker_run --volume $WORKSPACE/$INPUT_CERTIFICATES:/mosquitto-certs:ro"
fi

if [ -n "$INPUT_CONFIG" ]; then
  docker_run="$docker_run --volume $WORKSPACE/$INPUT_CONFIG:/mosquitto/config/mosquitto.conf:ro"
fi

docker_run="$docker_run eclipse-mosquitto:$INPUT_VERSION"

echo "$docker_run"
sh -c "$docker_run"
