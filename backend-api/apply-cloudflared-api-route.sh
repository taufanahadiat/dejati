#!/usr/bin/env bash
set -euo pipefail

sudo cp /etc/cloudflared/config.yml /etc/cloudflared/config.yml.bak.$(date +%Y%m%d%H%M%S)
sudo cp backend-api/cloudflared-config.proposed.yml /etc/cloudflared/config.yml
cloudflared --config /etc/cloudflared/config.yml tunnel ingress validate
sudo systemctl restart cloudflared
sleep 3
systemctl status cloudflared --no-pager
curl -sS https://api.dejaticoffee.com/health
echo
