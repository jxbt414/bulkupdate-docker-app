#!/bin/bash
# Run this once on a fresh EC2 Ubuntu instance to set up Docker and both apps.
# Usage: ssh ubuntu@13.55.159.1 'bash -s' < scripts/ec2-setup.sh

set -e

echo "=== Updating system ==="
sudo apt-get update
sudo apt-get upgrade -y

echo "=== Installing Docker ==="
sudo apt-get install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

echo "=== Adding ubuntu user to docker group ==="
sudo usermod -aG docker ubuntu

echo "=== Creating app directories ==="
mkdir -p ~/apps/bulkupdatetool
mkdir -p ~/apps/linguacafe

echo "=== Done! Log out and back in for docker group to take effect ==="
echo "Then clone your repos into ~/apps/"
