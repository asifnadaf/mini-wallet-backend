<?php

/**
 * API Integration Test Runner
 * 
 * This script runs all API integration tests in the correct order:
 * 1. API Integration Tests (routing, middleware, services)
 * 2. API Middleware Integration Tests
 * 3. API Service Integration Tests
 * 4. API Database Integration Tests
 * 5. API Integration Test Suite (comprehensive)
 */

echo "🔗 API Integration Test Suite\n";
echo "=============================\n\n";

// Test categories
$testCategories = [
    'API Integration Tests' => [
        'description' => 'Testing API routing, middleware, and service integration',
        'command' => 'php artisan test tests/Feature/ApiIntegrationTest.php',
        'files' => [
            'tests/Feature/ApiIntegrationTest.php'
        ]
    ],
    'API Middleware Integration Tests' => [
        'description' => 'Testing API middleware stack and configuration',
        'command' => 'php artisan test tests/Feature/ApiMiddlewareIntegrationTest.php',
        'files' => [
            'tests/Feature/ApiMiddlewareIntegrationTest.php'
        ]
    ],
    'API Service Integration Tests' => [
        'description' => 'Testing API service layer integration and dependencies',
        'command' => 'php artisan test tests/Feature/ApiServiceIntegrationTest.php',
        'files' => [
            'tests/Feature/ApiServiceIntegrationTest.php'
        ]
    ],
    'API Database Integration Tests' => [
        'description' => 'Testing API database layer integration and connectivity',
        'command' => 'php artisan test tests/Feature/ApiDatabaseIntegrationTest.php',
        'files' => [
            'tests/Feature/ApiDatabaseIntegrationTest.php'
        ]
    ],
    'API Integration Test Suite' => [
        'description' => 'Comprehensive API integration testing across all layers',
        'command' => 'php artisan test tests/Feature/ApiIntegrationTestSuite.php',
        'files' => [
            'tests/Feature/ApiIntegrationTestSuite.php'
        ]
    ]
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

foreach ($testCategories as $category => $config) {
    echo "📋 {$category}\n";
    echo "   {$config['description']}\n";
    echo "   " . str_repeat('-', strlen($config['description'])) . "\n\n";

    // Check if test files exist
    $missingFiles = [];
    foreach ($config['files'] as $file) {
        if (!file_exists($file)) {
            $missingFiles[] = $file;
        }
    }

    if (!empty($missingFiles)) {
        echo "   ❌ Missing test files:\n";
        foreach ($missingFiles as $file) {
            echo "      - {$file}\n";
        }
        echo "\n";
        continue;
    }

    // Run tests
    echo "   🏃 Running tests...\n";
    $output = [];
    $returnCode = 0;
    exec($config['command'] . ' 2>&1', $output, $returnCode);

    // Display results
    foreach ($output as $line) {
        echo "      {$line}\n";
    }

    if ($returnCode === 0) {
        echo "   ✅ {$category} passed\n\n";
        $passedTests++;
    } else {
        echo "   ❌ {$category} failed\n\n";
        $failedTests++;
    }

    $totalTests++;
}

// Summary
echo "📊 Test Summary\n";
echo "===============\n";
echo "Total test categories: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: {$failedTests}\n\n";

if ($failedTests === 0) {
    echo "🎉 All API integration tests passed!\n";
    echo "The API is fully integrated and working correctly.\n";
} else {
    echo "⚠️  Some tests failed. Please review the output above.\n";
    echo "The API integration may have issues that need attention.\n";
}

echo "\n";

// Additional information
echo "📚 API Integration Test Documentation\n";
echo "====================================\n";
echo "For detailed information about the API integration tests, see:\n";
echo "- tests/README.md - Comprehensive test documentation\n";
echo "- Individual test files for specific integration details\n";
echo "\n";

echo "🔧 Running Individual API Integration Tests\n";
echo "============================================\n";
echo "To run specific API integration test categories:\n";
echo "- API Integration: php artisan test tests/Feature/ApiIntegrationTest.php\n";
echo "- API Middleware: php artisan test tests/Feature/ApiMiddlewareIntegrationTest.php\n";
echo "- API Services: php artisan test tests/Feature/ApiServiceIntegrationTest.php\n";
echo "- API Database: php artisan test tests/Feature/ApiDatabaseIntegrationTest.php\n";
echo "- API Suite: php artisan test tests/Feature/ApiIntegrationTestSuite.php\n";
echo "\n";

echo "📈 API Integration Test Coverage\n";
echo "===============================\n";
echo "To run API integration tests with coverage:\n";
echo "php artisan test tests/Feature/Api*Test.php --coverage\n";
echo "\n";

echo "🔍 API Integration Test Categories\n";
echo "==================================\n";
echo "1. API Integration Tests - Core API functionality\n";
echo "2. API Middleware Integration Tests - Middleware stack\n";
echo "3. API Service Integration Tests - Service layer\n";
echo "4. API Database Integration Tests - Database layer\n";
echo "5. API Integration Test Suite - Comprehensive testing\n";
echo "\n";

echo "⚡ Performance Considerations\n";
echo "=============================\n";
echo "- API response times should be under 2 seconds\n";
echo "- Memory usage should be under 1MB per request\n";
echo "- Database connections should be efficient\n";
echo "- Service layer should handle errors gracefully\n";
echo "\n";

echo "🛡️ Security Considerations\n";
echo "===========================\n";
echo "- API should not require authentication for health checks\n";
echo "- CORS headers should be properly configured\n";
echo "- Input validation should be in place\n";
echo "- Error messages should not expose sensitive information\n";
echo "\n";
