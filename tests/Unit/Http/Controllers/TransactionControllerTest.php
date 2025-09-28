<?php

namespace Tests\Unit\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\Api\V1\Transactions\TransactionController;
use App\Services\TransactionService;
use App\Contracts\ApiResponseInterface;

class TransactionControllerTest extends TestCase
{
    protected TransactionController $controller;
    protected $transactionService;
    protected $apiResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionService = $this->createMock(TransactionService::class);
        $this->apiResponse = $this->createMock(ApiResponseInterface::class);

        $this->controller = new TransactionController(
            $this->transactionService,
            $this->apiResponse
        );
    }

    public function test_controller_can_be_instantiated()
    {
        $this->assertInstanceOf(TransactionController::class, $this->controller);
    }

    public function test_controller_has_required_methods()
    {
        $this->assertTrue(method_exists($this->controller, 'index'));
        $this->assertTrue(method_exists($this->controller, 'store'));
    }

    public function test_controller_uses_correct_service()
    {
        $this->assertInstanceOf(TransactionService::class, $this->transactionService);
    }

    public function test_controller_uses_correct_api_response()
    {
        $this->assertInstanceOf(ApiResponseInterface::class, $this->apiResponse);
    }
}
