#!/bin/sh

VERSION=$1
PORTS=$2
CERTIFICATES=$3
CONFIG=$4

echo "Certificates: $CERTIFICATES"
ls -lsa $CERTIFICATES

echo "Config: $CONFIG"
ls -lsa $CONFIG

docker_run="docker run --detach --name emqx"

for i in $(echo $PORTS | tr " " "\n")
do
  docker_run="$docker_run --publish $i"
done

if [ -n "$CERTIFICATES" ]; then
  docker_run="$docker_run --volume $CERTIFICATES:/emqx-certs:ro"
fi

if [ -n "$CONFIG" ]; then
  docker_run="$docker_run --volume $CONFIG:/opt/emqx/etc/emqx_custom.conf:ro"
fi

docker_run="$docker_run emqx/emqx:$VERSION"

if [ -n "$CONFIG" ]; then
  docker_run="$docker_run /bin/sh -c \"cp /opt/emqx/etc/emqx_custom.conf /opt/emqx/etc/emqx.conf && /bin/sh /usr/bin/start.sh\""
fi

echo "$docker_run"
sh -c "$docker_run"
