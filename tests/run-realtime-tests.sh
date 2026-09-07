#!/bin/bash
# Test Runner for Real-time Architecture

echo "========================================"
echo "Running Real-time Architecture Tests"
echo "========================================"
echo ""

# Run unit tests
echo "1. Running Unit Tests..."
php artisan test --testsuite="Realtime Unit" --compact
echo ""

# Run feature tests
echo "2. Running Feature Tests..."
php artisan test --testsuite="Realtime Feature" --compact
echo ""

# Run Pest tests
echo "3. Running Pest Tests (realtime group)..."
php artisan test --filter=realtime --compact
echo ""

# Run with coverage (optional)
if [ "$1" == "--coverage" ]; then
    echo "4. Running with Coverage..."
    php artisan test --testsuite="Realtime Unit" --testsuite="Realtime Feature" --coverage --min=80
    echo ""
fi

echo "========================================"
echo "All Real-time Tests Completed"
echo "========================================"