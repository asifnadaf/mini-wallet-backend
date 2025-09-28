<?php

/**
 * API Test Runner
 * 
 * This script runs all API tests in the correct order:
 * 1. Unit Tests (individual components)
 * 2. Functional Tests (API endpoints)
 * 3. Integration Tests (complete workflows)
 */

echo "🚀 API Test Suite\n";
echo "=================\n\n";

// Test categories
$testCategories = [
    'API Unit Tests' => [
        'description' => 'Testing individual API components in isolation',
        'command' => 'php artisan test tests/Unit/Http/Controllers/RegisterUserControllerTest.php tests/Unit/Services/UserRegistrationServiceTest.php tests/Unit/Http/Requests/RegisterUserRequestTest.php tests/Unit/Http/Resources/UserResourceTest.php',
        'files' => [
            'tests/Unit/Http/Controllers/RegisterUserControllerTest.php',
            'tests/Unit/Services/UserRegistrationServiceTest.php',
            'tests/Unit/Http/Requests/RegisterUserRequestTest.php',
            'tests/Unit/Http/Resources/UserResourceTest.php'
        ]
    ],
    'API Functional Tests' => [
        'description' => 'Testing API endpoints and their behavior',
        'command' => 'php artisan test tests/Feature/Api/UserRegistrationTest.php tests/Feature/Api/HealthCheckTest.php',
        'files' => [
            'tests/Feature/Api/UserRegistrationTest.php',
            'tests/Feature/Api/HealthCheckTest.php'
        ]
    ],
    'API Integration Tests' => [
        'description' => 'Testing complete API workflows and integration',
        'command' => 'php artisan test tests/Feature/Api/ApiIntegrationTest.php tests/Feature/Api/ApiWorkflowTest.php',
        'files' => [
            'tests/Feature/Api/ApiIntegrationTest.php',
            'tests/Feature/Api/ApiWorkflowTest.php'
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
    echo "🎉 All API tests passed!\n";
    echo "The API is working correctly.\n";
} else {
    echo "⚠️  Some tests failed. Please review the output above.\n";
    echo "The API may have issues that need attention.\n";
}

echo "\n";

// Additional information
echo "📚 API Test Documentation\n";
echo "=========================\n";
echo "For detailed information about the API tests, see:\n";
echo "- tests/README.md - Comprehensive test documentation\n";
echo "- Individual test files for specific test details\n";
echo "\n";

echo "🔧 Running Individual API Tests\n";
echo "===============================\n";
echo "To run specific API test categories:\n";
echo "- Unit tests: php artisan test tests/Unit/Http/Controllers/ tests/Unit/Services/ tests/Unit/Http/Requests/ tests/Unit/Http/Resources/\n";
echo "- Functional tests: php artisan test tests/Feature/Api/UserRegistrationTest.php tests/Feature/Api/HealthCheckTest.php\n";
echo "- Integration tests: php artisan test tests/Feature/Api/ApiIntegrationTest.php tests/Feature/Api/ApiWorkflowTest.php\n";
echo "\n";

echo "📈 API Test Coverage\n";
echo "====================\n";
echo "To run API tests with coverage:\n";
echo "php artisan test tests/Unit/Http/ tests/Unit/Services/ tests/Feature/Api/ --coverage\n";
echo "\n";

echo "🔍 API Test Categories\n";
echo "======================\n";
echo "1. API Unit Tests - Individual components (controllers, services, requests, resources)\n";
echo "2. API Functional Tests - API endpoints and their behavior\n";
echo "3. API Integration Tests - Complete workflows and system integration\n";
echo "\n";

echo "⚡ Performance Considerations\n";
echo "=============================\n";
echo "- API response times should be under 2 seconds\n";
echo "- Memory usage should be under 5MB per request\n";
echo "- Database operations should be efficient\n";
echo "- Service layer should handle errors gracefully\n";
echo "\n";

echo "🛡️ Security Considerations\n";
echo "==========================\n";
echo "- API should validate all input data\n";
echo "- Rate limiting should be properly configured\n";
echo "- Authentication should be enforced where needed\n";
echo "- Error messages should not expose sensitive information\n";
echo "\n";

echo "🔧 API Endpoints Tested\n";
echo "=======================\n";
echo "- GET /api/v1/health-check - Health check endpoint\n";
echo "- POST /api/v1/register - User registration endpoint\n";
echo "\n";

echo "📋 Test Scenarios Covered\n";
echo "=========================\n";
echo "- Successful user registration\n";
echo "- Validation error handling\n";
echo "- Rate limiting\n";
echo "- Authentication middleware\n";
echo "- Database transactions\n";
echo "- Service integration\n";
echo "- Error recovery\n";
echo "- Performance testing\n";
echo "- Security testing\n";
echo "\n";
