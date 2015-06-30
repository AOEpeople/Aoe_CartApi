#!/bin/bash

URL=$1
SESSION=$2
KEY=$3
VALUE=$4

if [ "${URL}" == "" -o "${SESSION}" == "" -o "${KEY}" == "" ]; then
    echo "Usage: $0 <url> <session_token> <key> [<value>]"
    exit 1
fi

echo "POST ${URL}"
DATA="{\"${KEY}\":\"${VALUE}\"}"
curl -v -H "Content-Type: application/json" -H "Cookie: frontend=${SESSION}" -X POST -d "${DATA}" "${URL}" | jq '.'
