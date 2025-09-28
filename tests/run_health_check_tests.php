<?php

/**
 * Health Check Test Runner
 * 
 * This script runs all health check related tests in the correct order:
 * 1. Unit tests (individual components)
 * 2. Functional tests (API endpoints)
 * 3. End-to-end tests (complete workflows)
 */

echo "🏥 Health Check API Test Suite\n";
echo "==============================\n\n";

// Test categories
$testCategories = [
    'Unit Tests' => [
        'description' => 'Testing individual components in isolation',
        'command' => 'php artisan test tests/Unit/',
        'files' => [
            'tests/Unit/Services/HealthCheckServiceTest.php',
            'tests/Unit/Services/HealthCheckResponseFormatterTest.php',
            'tests/Unit/Services/HealthCheckers/ComputeHealthCheckerTest.php',
            'tests/Unit/Services/HealthCheckers/MySQLHealthCheckerTest.php',
            'tests/Unit/Http/Controllers/ServerStatusControllerTest.php'
        ]
    ],
    'Functional Tests' => [
        'description' => 'Testing API endpoints and their behavior',
        'command' => 'php artisan test tests/Feature/HealthCheckTest.php',
        'files' => [
            'tests/Feature/HealthCheckTest.php',
            'tests/Feature/HealthCheckIntegrationTest.php'
        ]
    ],
    'End-to-End Tests' => [
        'description' => 'Testing complete workflows from request to response',
        'command' => 'php artisan test tests/Feature/HealthCheckE2ETest.php',
        'files' => [
            'tests/Feature/HealthCheckE2ETest.php'
        ]
    ],
    'API Integration Tests' => [
        'description' => 'Testing complete API integration across all layers',
        'command' => 'php artisan test tests/Feature/ApiIntegrationTest.php tests/Feature/ApiMiddlewareIntegrationTest.php tests/Feature/ApiServiceIntegrationTest.php tests/Feature/ApiDatabaseIntegrationTest.php tests/Feature/ApiIntegrationTestSuite.php',
        'files' => [
            'tests/Feature/ApiIntegrationTest.php',
            'tests/Feature/ApiMiddlewareIntegrationTest.php',
            'tests/Feature/ApiServiceIntegrationTest.php',
            'tests/Feature/ApiDatabaseIntegrationTest.php',
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
    echo "🎉 All health check tests passed!\n";
    echo "The health check API is working correctly.\n";
} else {
    echo "⚠️  Some tests failed. Please review the output above.\n";
    echo "The health check API may have issues that need attention.\n";
}

echo "\n";

// Additional information
echo "📚 Test Documentation\n";
echo "=====================\n";
echo "For detailed information about the test suite, see:\n";
echo "- tests/README.md - Comprehensive test documentation\n";
echo "- Individual test files for specific test details\n";
echo "\n";

echo "🔧 Running Individual Tests\n";
echo "===========================\n";
echo "To run specific test categories:\n";
echo "- Unit tests: php artisan test tests/Unit/\n";
echo "- Functional tests: php artisan test tests/Feature/HealthCheckTest.php\n";
echo "- Integration tests: php artisan test tests/Feature/HealthCheckIntegrationTest.php\n";
echo "- End-to-end tests: php artisan test tests/Feature/HealthCheckE2ETest.php\n";
echo "\n";

echo "📈 Test Coverage\n";
echo "================\n";
echo "To run tests with coverage:\n";
echo "php artisan test --coverage\n";
echo "\n";
