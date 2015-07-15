#!/bin/bash

URL=$1
SESSION=$2
DATA=$3
COOKIE=$4

if [ "${URL}" == "" -o "${SESSION}" == "" -o "${DATA}" == "" ]; then
    echo "Usage: $0 <url> <session_token> <json string>"
    exit 1
fi

echo "POST ${URL}"
curl -v -H "Content-Type: application/json" -H "Cookie: frontend=${SESSION}; ${COOKIE}" -X POST -d "${DATA}" "${URL}" | jq '.'
