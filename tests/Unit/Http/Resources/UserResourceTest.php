<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    public function test_to_array_returns_correct_structure()
    {
        $user = new User();
        $user->id = 1;
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->email_verified_at = '2023-01-01 00:00:00';

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('email_verified_at', $array);

        $this->assertEquals(1, $array['id']);
        $this->assertEquals('John Doe', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
        $this->assertEquals('2023-01-01 00:00:00', $array['email_verified_at']);
    }

    public function test_to_array_excludes_hidden_attributes()
    {
        $user = new User([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'hashed-password',
            'remember_token' => 'remember-token',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request());

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('created_at', $array);
        $this->assertArrayNotHasKey('updated_at', $array);
    }

    public function test_to_array_handles_null_email_verified_at()
    {
        $user = new User([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => null,
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request());

        $this->assertNull($array['email_verified_at']);
    }

    public function test_to_array_handles_empty_user()
    {
        $user = new User();

        $resource = new UserResource($user);
        $array = $resource->toArray(new Request());

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('email_verified_at', $array);
    }

    public function test_to_array_returns_consistent_structure()
    {
        $user1 = new User([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => '2023-01-01 00:00:00',
        ]);

        $user2 = new User([
            'id' => 2,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'email_verified_at' => null,
        ]);

        $resource1 = new UserResource($user1);
        $resource2 = new UserResource($user2);

        $array1 = $resource1->toArray(new Request());
        $array2 = $resource2->toArray(new Request());

        $this->assertEquals(array_keys($array1), array_keys($array2));
    }

    public function test_to_array_with_different_request_objects()
    {
        $user = new User([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => '2023-01-01 00:00:00',
        ]);

        $resource = new UserResource($user);

        $request1 = new Request();
        $request2 = Request::create('/test', 'GET');

        $array1 = $resource->toArray($request1);
        $array2 = $resource->toArray($request2);

        $this->assertEquals($array1, $array2);
    }
}
