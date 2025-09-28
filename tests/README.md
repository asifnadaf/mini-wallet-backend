# Health Check API Test Suite

This directory contains comprehensive test cases for the health check API endpoint (`/api/v1/health-check`).

## Test Structure

### Unit Tests
- **Location**: `tests/Unit/`
- **Purpose**: Test individual components in isolation
- **Files**:
  - `Services/HealthCheckServiceTest.php` - Tests the health check service
  - `Services/HealthCheckResponseFormatterTest.php` - Tests response formatting
  - `Services/HealthCheckers/ComputeHealthCheckerTest.php` - Tests compute health checker
  - `Services/HealthCheckers/MySQLHealthCheckerTest.php` - Tests MySQL health checker
  - `Http/Controllers/ServerStatusControllerTest.php` - Tests the controller

### Functional Tests
- **Location**: `tests/Feature/`
- **Purpose**: Test API endpoints and their behavior
- **Files**:
  - `HealthCheckTest.php` - Tests the health check endpoint functionality
  - `HealthCheckIntegrationTest.php` - Tests integration between components
  - `HealthCheckE2ETest.php` - End-to-end tests for complete workflows

## Test Categories

### 1. Unit Tests
Test individual components in isolation using mocks and stubs.

**HealthCheckService Tests:**
- ✅ Returns collection of health statuses
- ✅ Includes all registered checkers
- ✅ Returns ServerStatus enum values
- ✅ Can add new checkers
- ✅ Prevents duplicate checkers

**HealthCheckResponseFormatter Tests:**
- ✅ Formats healthy services correctly
- ✅ Formats unhealthy services correctly
- ✅ Handles unknown status
- ✅ Handles empty collections

**Health Checker Tests:**
- ✅ ComputeHealthChecker always returns UP
- ✅ MySQLHealthChecker tests database connectivity
- ✅ MySQLHealthChecker handles database failures
- ✅ All checkers implement HealthCheckable interface

**Controller Tests:**
- ✅ Returns successful response for healthy services
- ✅ Handles exceptions gracefully
- ✅ Logs errors appropriately

### 2. Functional Tests
Test API endpoints and their behavior with real dependencies.

**Health Check Endpoint Tests:**
- ✅ Returns successful response structure
- ✅ Returns healthy status when all services up
- ✅ Includes all expected services
- ✅ Handles database failures
- ✅ Returns correct HTTP methods
- ✅ Returns JSON content type
- ✅ Handles multiple service failures
- ✅ Handles mixed service statuses

### 3. End-to-End Tests
Test complete workflows from request to response.

**Complete Flow Tests:**
- ✅ All services healthy workflow
- ✅ Database failure workflow
- ✅ Service exception workflow
- ✅ Component integration
- ✅ Performance and reliability
- ✅ Concurrent requests handling
- ✅ Logging behavior
- ✅ Response format consistency

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Categories
```bash
# Unit tests only
php artisan test tests/Unit/

# Functional tests only
php artisan test tests/Feature/HealthCheckTest.php

# End-to-end tests only
php artisan test tests/Feature/HealthCheckE2ETest.php
```

### Run with Coverage
```bash
php artisan test --coverage
```

## Test Data and Scenarios

### Healthy Service Scenarios
- All services return `UP` status
- Database connection successful
- No exceptions thrown

### Unhealthy Service Scenarios
- Database connection fails
- Service throws exceptions
- Mixed service statuses (some up, some down)

### Error Scenarios
- Service unavailable exceptions
- Database connection timeouts
- Invalid service responses

## Mocking Strategy

### Unit Tests
- Mock all external dependencies
- Use Mockery for complex mocking
- Test components in complete isolation

### Functional Tests
- Use real services where possible
- Mock only problematic dependencies (database failures)
- Test real integration between components

### End-to-End Tests
- Use real services and dependencies
- Test complete request/response cycle
- Verify performance and reliability

## Assertions

### Response Structure
```php
$response->assertStatus(200)
    ->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'status',
            'services' => [
                '*' => [
                    'service',
                    'status',
                    'last_checked'
                ]
            ]
        ]
    ]);
```

### Service Status
```php
$response->assertJson([
    'success' => true,
    'data' => [
        'status' => 'healthy'
    ]
]);
```

### Error Handling
```php
$response->assertStatus(503)
    ->assertJson([
        'success' => false,
        'message' => 'Unable to complete health check'
    ]);
```

## Performance Considerations

- Health checks should complete within 2 seconds
- Memory usage should be under 1MB
- Concurrent requests should be handled properly
- Database queries should be optimized

## Maintenance

### Adding New Health Checkers
1. Create the checker class implementing `HealthCheckable`
2. Add unit tests for the new checker
3. Update integration tests to include the new service
4. Update end-to-end tests to verify the new service

### Modifying Response Format
1. Update unit tests for `HealthCheckResponseFormatter`
2. Update functional tests for response structure
3. Update end-to-end tests for complete flow
4. Update documentation

### Adding New Test Scenarios
1. Identify the test category (unit/functional/e2e)
2. Create appropriate test methods
3. Use proper mocking strategy
4. Add comprehensive assertions
5. Update this documentation

## Best Practices

1. **Test Isolation**: Each test should be independent
2. **Clear Naming**: Test method names should describe the scenario
3. **Comprehensive Assertions**: Test both success and failure cases
4. **Proper Mocking**: Mock external dependencies appropriately
5. **Performance Testing**: Include performance and reliability tests
6. **Documentation**: Keep test documentation up to date
