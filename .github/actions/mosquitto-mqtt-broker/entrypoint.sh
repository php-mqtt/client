#!/bin/sh

ln -ls $GITHUB_WORKSPACE

docker_run="docker run --detach --name mosquitto"

for i in $(echo $INPUT_PORTS | tr " " "\n")
do
  docker_run="$docker_run --publish $i"
done

if [ -n "$INPUT_CERTIFICATES" ]; then
  docker_run="$docker_run --volume $GITHUB_WORKSPACE/$INPUT_CERTIFICATES:/mosquitto-certs"
fi

if [ -n "$INPUT_CONFIG" ]; then
  docker_run="$docker_run --volume $GITHUB_WORKSPACE/$INPUT_CONFIG:/mosquitto/config/mosquitto.conf"
fi

docker_run="$docker_run eclipse-mosquitto:$INPUT_VERSION"

echo "$docker_run"
sh -c "$docker_run"
