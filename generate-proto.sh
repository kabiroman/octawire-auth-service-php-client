#!/bin/bash

# Скрипт для генерации PHP классов из proto файлов
# Требуется: protoc и grpc_php_plugin

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROTO_DIR="$SCRIPT_DIR/../../api"
OUTPUT_DIR="$SCRIPT_DIR/src/Generated"

# Проверка наличия protoc
if ! command -v protoc &> /dev/null; then
    echo "Error: protoc not found. Install from https://grpc.io/docs/protoc-installation/"
    exit 1
fi

# Проверка наличия grpc_php_plugin
if ! command -v grpc_php_plugin &> /dev/null; then
    echo "Error: grpc_php_plugin not found. Install grpc extension for PHP."
    echo "See: https://github.com/grpc/grpc/blob/master/src/php/README.md"
    exit 1
fi

# Создание директории для сгенерированных файлов
mkdir -p "$OUTPUT_DIR"

echo "Generating PHP classes from proto files..."
echo "Proto directory: $PROTO_DIR"
echo "Output directory: $OUTPUT_DIR"

# Генерация PHP классов
protoc \
    --php_out="$OUTPUT_DIR" \
    --grpc_out="$OUTPUT_DIR" \
    --plugin=protoc-gen-grpc="$(which grpc_php_plugin)" \
    --proto_path="$PROTO_DIR" \
    "$PROTO_DIR/jwt.proto"

echo "✅ PHP classes generated successfully in $OUTPUT_DIR"
echo ""
echo "Generated files:"
find "$OUTPUT_DIR" -name "*.php" | head -10

