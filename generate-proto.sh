#!/bin/bash

# TCP/JSON клиент больше не использует gRPC/Protobuf генерацию.
# Файл оставлен для совместимости Makefile и будет выведен в депрекейт-сообщение.

set -euo pipefail

cat <<'EOF'
⚠️  Proto generation is no longer required for the PHP TCP client.

The Go Auth Service remains the source of truth for proto definitions.
TCP/JSON payloads are derived from those models via protojson on the server
side, поэтому PHP-клиент больше не генерирует классы и не требует gRPC
плагина/расширений. См. docs/TODO.md и docs/ROADMAP.md для деталей.
EOF

