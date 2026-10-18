#!/usr/bin/env bash
# Operator-only. No SSH, downloads, Composer installation, migrations or DB restore.
set -Eeuo pipefail
umask 077
command -v python3 >/dev/null || { echo 'BLOCKED: Python 3.10+ is required'; exit 1; }
python3 -c 'import sys; sys.exit(0 if sys.version_info >= (3, 10) else "BLOCKED: Python 3.10+ required")'
python3 -c 'import fcntl, gzip, ssl, tarfile' || { echo 'BLOCKED: required Python modules unavailable'; exit 1; }
script_dir=$(cd -- "$(dirname -- "$0")" && pwd)
exec python3 "$script_dir/deploy.py" "$@"
