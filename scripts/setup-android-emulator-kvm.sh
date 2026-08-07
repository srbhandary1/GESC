#!/usr/bin/env bash
set -euo pipefail
# One-time host privilege step for Android emulator acceleration.
# Must be run in an interactive terminal that can prompt for your sudo password:
#   bash scripts/setup-android-emulator-kvm.sh

sudo usermod -aG kvm "$USER"
sudo setfacl -m "u:${USER}:rw" /dev/kvm
echo "KVM group membership and ACL updated for $USER"
echo "If group membership is new, open a fresh login shell, then run:"
echo "  bash scripts/run-android-emulator-matrix.sh"
