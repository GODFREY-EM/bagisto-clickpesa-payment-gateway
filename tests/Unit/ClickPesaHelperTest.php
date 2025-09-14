<?php

namespace Bruno\ClickPesa\Tests\Unit;

use Tests\TestCase;
use Bruno\ClickPesa\Helpers\ClickPesaHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;

class ClickPesaHelperTest extends TestCase
{
    protected $clickpesa;
    protected $mockHandler;

    public function setUp(): void
    {
        parent::setUp();
        
        // Mock config
        Config::set('payment_methods.clickpesa', [
            'sandbox' => true,
            'consumer_key' => 'test_key',
            'consumer_secret' => 'test_secret',
            'shortcode' => '174379',
            'passkey' => 'test_passkey'
        ]);

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $this->clickpesa = new ClickPesaHelper();
        $this->clickpesa->setClient($client);
    }

    /** @test */
    public function it_can_get_access_token()
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token',
                'expires_in' => '3599'
            ]))
        );

        $token = $this->clickpesa->getAccessToken();
        $this->assertEquals('test_token', $token);
    }

    /** @test */
    public function it_handles_failed_access_token()
    {
        $this->mockHandler->append(
            new Response(401, [], json_encode([
                'errorCode' => 'Invalid Credentials',
                'errorMessage' => 'Bad Request - Invalid Credentials'
            ]))
        );

        $this->expectException(\Exception::class);
        $this->clickpesa->getAccessToken();
    }

    /** @test */
    public function it_can_initiate_stk_push()
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token'
            ])),
            new Response(200, [], json_encode([
                'MerchantRequestID' => '12345',
                'CheckoutRequestID' => '67890',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success'
            ]))
        );

        $response = $this->clickpesa->initiateSTKPush([
            'phone' => '254712345678',
            'amount' => 100,
            'reference' => '12345',
            'description' => 'Test Payment'
        ]);

        $this->assertTrue($response['success']);
        $this->assertEquals('STK push initiated successfully', $response['message']);
    }

    /** @test */
    public function it_handles_failed_stk_push()
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token'
            ])),
            new Response(400, [], json_encode([
                'ResponseCode' => '1',
                'ResponseDescription' => 'Failed'
            ]))
        );

        $response = $this->clickpesa->initiateSTKPush([
            'phone' => '254712345678',
            'amount' => 100,
            'reference' => '12345',
            'description' => 'Test Payment'
        ]);

        $this->assertFalse($response['success']);
    }

    /** @test */
    public function it_can_validate_successful_callback()
    {
        $callbackData = [
            'Body' => [
                'stkCallback' => [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CheckoutRequestID' => '12345',
                    'CallbackMetadata' => [
                        'Item' => [
                            [
                                'Name' => 'Amount',
                                'Value' => 100
                            ],
                            [
                                'Name' => 'ClickPesaReceiptNumber',
                                'Value' => 'PXL12345'
                            ],
                            [
                                'Name' => 'PhoneNumber',
                                'Value' => '254712345678'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->clickpesa->validateCallback($callbackData);

        $this->assertTrue($response['success']);
        $this->assertEquals('COMPLETED', $response['status']);
        $this->assertEquals('12345', $response['reference']);
        $this->assertEquals('PXL12345', $response['receipt']);
    }

    /** @test */
    public function it_can_validate_failed_callback()
    {
        $callbackData = [
            'Body' => [
                'stkCallback' => [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Failed',
                    'CheckoutRequestID' => '12345'
                ]
            ]
        ];

        $response = $this->clickpesa->validateCallback($callbackData);

        $this->assertFalse($response['success']);
        $this->assertEquals('FAILED', $response['status']);
    }

    /** @test */
    public function it_can_query_stk_status()
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token'
            ])),
            new Response(200, [], json_encode([
                'ResponseCode' => '0',
                'ResultDesc' => 'The service request is processed successfully.',
                'ResultCode' => '0'
            ]))
        );

        $response = $this->clickpesa->querySTKStatus('12345');

        $this->assertTrue($response['success']);
        $this->assertEquals('COMPLETED', $response['status']);
    }
}
