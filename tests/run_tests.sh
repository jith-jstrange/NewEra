#!/bin/bash

# Simple test runner script to verify test syntax
set -e

cd "$(dirname "${BASH_SOURCE[0]}")/.."

echo "Checking PHP syntax for test files..."

for file in tests/*Test.php; do
    if [ -f "$file" ]; then
        echo "Checking syntax: $file"
        php -l "$file" || exit 1
    fi
done

echo "All test files have valid PHP syntax!"
