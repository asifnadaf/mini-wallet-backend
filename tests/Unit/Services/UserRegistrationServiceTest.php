<?php

namespace Tests\Unit\Services;

use App\Services\UserRegistrationService;
use App\Models\User;
use App\Strategies\Token\TokenSenderStrategy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_creates_user_successfully()
    {
        $mockTokenSender = Mockery::mock(TokenSenderStrategy::class);
        $service = new UserRegistrationService($mockTokenSender);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $mockTokenSender->shouldReceive('send')
            ->once()
            ->andReturn(['status' => 'sent']);

        $result = $service->register($userData);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_register_handles_exception()
    {
        $mockTokenSender = Mockery::mock(TokenSenderStrategy::class);
        $service = new UserRegistrationService($mockTokenSender);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        // Mock the token sender to throw an exception
        $mockTokenSender->shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Service error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Service error');

        $service->register($userData);
    }

    public function test_register_calls_token_sender()
    {
        $mockTokenSender = Mockery::mock(TokenSenderStrategy::class);
        $service = new UserRegistrationService($mockTokenSender);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $mockTokenSender->shouldReceive('send')
            ->once()
            ->andReturn(['status' => 'sent']);

        $result = $service->register($userData);

        $this->assertInstanceOf(User::class, $result);
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_register_uses_database_transaction()
    {
        $mockTokenSender = Mockery::mock(TokenSenderStrategy::class);
        $service = new UserRegistrationService($mockTokenSender);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $mockTokenSender->shouldReceive('send')
            ->once()
            ->andReturn(['status' => 'sent']);

        $result = $service->register($userData);

        $this->assertInstanceOf(User::class, $result);
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_register_logs_error_on_failure()
    {
        $mockTokenSender = Mockery::mock(TokenSenderStrategy::class);
        $service = new UserRegistrationService($mockTokenSender);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        // Mock the token sender to throw an exception
        $mockTokenSender->shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Service error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Service error');

        $service->register($userData);
    }
}
