<?php

namespace Bruno\ClickPesa\Tests\Unit;

use Tests\TestCase;
use Bruno\ClickPesa\Http\Controllers\ClickPesaController;
use Bruno\ClickPesa\Helpers\ClickPesaHelper;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Checkout\Facades\Cart;
use Illuminate\Http\Request;
use Mockery;
use Webkul\Sales\Models\Order;

class ClickPesaControllerTest extends TestCase
{
    protected $controller;
    protected $orderRepository;
    protected $invoiceRepository;
    protected $clickpesaHelper;

    public function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->invoiceRepository = Mockery::mock(InvoiceRepository::class);
        $this->clickpesaHelper = Mockery::mock(ClickPesaHelper::class);

        $this->controller = new ClickPesaController(
            $this->orderRepository,
            $this->invoiceRepository,
            $this->clickpesaHelper
        );
    }

    /** @test */
    public function it_can_process_payment()
    {
        $request = new Request([
            'clickpesa_phone' => '0712345678'
        ]);

        $cart = Mockery::mock();
        $cart->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $cart->shouldReceive('getAttribute')->with('grand_total')->andReturn(100);

        Cart::shouldReceive('getCart')->andReturn($cart);
        Cart::shouldReceive('prepareDataForOrder')->andReturn([]);

        $order = new Order();
        $order->id = 1;
        $order->grand_total = 100;

        $this->orderRepository->shouldReceive('create')
            ->andReturn($order);

        $this->clickpesaHelper->shouldReceive('initiateSTKPush')
            ->with([
                'phone' => '254712345678',
                'amount' => 100,
                'reference' => 1,
                'description' => 'Payment for Order #1'
            ])
            ->andReturn([
                'success' => true,
                'message' => 'STK push initiated successfully',
                'data' => [
                    'CheckoutRequestID' => '12345'
                ]
            ]);

        $response = $this->controller->process($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('STK push sent successfully', $data['message']);
    }

    /** @test */
    public function it_handles_failed_stk_push()
    {
        $request = new Request([
            'clickpesa_phone' => '0712345678'
        ]);

        $cart = Mockery::mock();
        $cart->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $cart->shouldReceive('getAttribute')->with('grand_total')->andReturn(100);

        Cart::shouldReceive('getCart')->andReturn($cart);
        Cart::shouldReceive('prepareDataForOrder')->andReturn([]);

        $order = new Order();
        $order->id = 1;
        $order->grand_total = 100;

        $this->orderRepository->shouldReceive('create')
            ->andReturn($order);

        $this->orderRepository->shouldReceive('delete')
            ->with(1)
            ->once();

        $this->clickpesaHelper->shouldReceive('initiateSTKPush')
            ->andReturn([
                'success' => false,
                'message' => 'Failed to initiate STK push'
            ]);

        $response = $this->controller->process($request);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Failed to initiate STK push', $data['message']);
    }

    /** @test */
    public function it_can_check_payment_status()
    {
        $order = new Order();
        $order->id = 1;
        $order->clickpesa_status = 'PENDING';
        $order->clickpesa_checkout_request_id = '12345';

        $this->orderRepository->shouldReceive('findOrFail')
            ->with(1)
            ->andReturn($order);

        $this->clickpesaHelper->shouldReceive('querySTKStatus')
            ->with('12345')
            ->andReturn([
                'status' => 'COMPLETED'
            ]);

        $this->orderRepository->shouldReceive('update')
            ->with([
                'status' => Order::STATUS_PROCESSING,
                'clickpesa_status' => 'COMPLETED'
            ], 1)
            ->once();

        $order->shouldReceive('canInvoice')
            ->andReturn(true);

        $this->invoiceRepository->shouldReceive('create')
            ->once();

        $response = $this->controller->checkStatus(1);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
    }

    /** @test */
    public function it_can_handle_callback()
    {
        $request = new Request([], [
            'Body' => [
                'stkCallback' => [
                    'ResultCode' => 0,
                    'CheckoutRequestID' => '12345'
                ]
            ]
        ]);

        $order = new Order();
        $order->id = 1;

        $this->clickpesaHelper->shouldReceive('validateCallback')
            ->andReturn([
                'success' => true,
                'status' => 'COMPLETED',
                'reference' => '12345',
                'receipt' => 'PXL12345',
                'phone' => '254712345678',
                'amount' => 100
            ]);

        $this->orderRepository->shouldReceive('findOneByField')
            ->with('clickpesa_checkout_request_id', '12345')
            ->andReturn($order);

        $this->orderRepository->shouldReceive('update')
            ->with([
                'status' => Order::STATUS_PROCESSING,
                'clickpesa_receipt' => 'PXL12345',
                'clickpesa_phone' => '254712345678',
                'clickpesa_amount' => 100,
                'clickpesa_status' => 'COMPLETED'
            ], 1)
            ->once();

        $order->shouldReceive('canInvoice')
            ->andReturn(true);

        $this->invoiceRepository->shouldReceive('create')
            ->once();

        $response = $this->controller->handleCallback($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
    }
}
