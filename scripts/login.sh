#!/bin/bash

URL=$1
USERNAME=$2
PASSWORD=$3

if [ "${URL}" == "" -o "${USERNAME}" == "" -o "${PASSWORD}" == "" ]; then
    echo "Usage: $0 <url> <userid> <password>"
    exit 1
fi

DATA="{\"login\":\"${USERNAME}\", \"password\":\"${PASSWORD}\"}"
SESSION=`curl -s -H "Content-Type: application/json" -X POST -d "${DATA}" -D - "${URL}" | grep 'Set-Cookie' | tail -n1 | sed -En 's/^Set-Cookie: frontend=([^;]+).*/\1/p'`

echo $SESSION
