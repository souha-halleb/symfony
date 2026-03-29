#!/bin/sh
printf '{"email":"test@example.com","password":"secret123"}' > /tmp/register_payload.json
curl -v -H "Content-Type: application/json" --data @/tmp/register_payload.json http://nginx/api/auth/register
