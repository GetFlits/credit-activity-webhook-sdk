<?php

// use Flits\CreditActivityWebhook\CreditActivityWebhookProvider;

use Carbon\Carbon;
use Flits\CreditActivityWebhook\API\SendWebhook;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

require_once 'vendor/autoload.php';

class CreditActivityWebhookTest extends TestCase {

    public $client;
    public $CREDIT_UPDATE_EVENT_NAME = 'flits_store_credit_adjusted';

    protected function setUp(): void {
        $mock = new MockHandler([
            new Response(200, [], '{"message": "Success"}'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $this->client = new Client(['handler' => $handlerStack]);
    }

    public function createApplication() {
        $app = require __DIR__ . '/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.testing');
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        return $app;
    }

    protected function tearDown(): void {
        parent::tearDown();
        Mockery::close();
    }

    public function getHeaders() {
        $config['headers'] = [
            "Content-Type" => "application/json"
        ];
        $config['base_url'] = "";
        return $config;
    }

    public function UploadAPI($updatedCustomer, $extraData, $event_name) {
        $eventName = $this->CREDIT_UPDATE_EVENT_NAME;
        $type = ((isset($extraData->rule_id)) && ($extraData->rule_id == -1)) ? 'Adjusted by store owner' : 'Automatic adjustment by flits rule manager';
        $extraData->value = $extraData->value ?? 0;
        $extraData->module_on = $extraData->module_on ?? '';
        $extraData->comment = $extraData->comment ?? '';

        $requestData = '{
            "data": {
                "adjusted_credit": ' . $extraData->value . ',
                "old_credit":' . round((floatval(($updatedCustomer['credits'] / 100)) - floatval($extraData->value)), 2) . ',
                "module_on":"' . $extraData->module_on . '",
                "reason":"' . $extraData->comment . '",
                "type":"' . $type . '",
                "current_credit":' . ($updatedCustomer['credits'] / 100) . '
            }
        }';
        $creditActivitySendWebhook = new SendWebhook($this->getHeaders());
        $response = $creditActivitySendWebhook->POST($requestData);
        $statusCode = $response->status;
        $this->assertEquals("success", $statusCode);
    }

    public function testCreditSave() {
        $customerIdToSearch = 7526853083456;

        $customerMock = Mockery::mock(Customer::class);

        $customerMock->shouldReceive('where')->with('customer_id', $customerIdToSearch)->once()->andReturnSelf();

        $customerMock->shouldReceive('first')->once()->andReturn([
            'id' => 1,
            'customer_id' => $customerIdToSearch,
            'name' => 'Vaibhav Rathod',
            'email' => 'vaibhav@getflits.com',
            'phone' => '+919876543211',
            'gender' => 'male',
            'Company_name' => 'Flits',
            'token' => '91f4cea3d2641c5abbf7c73502eb984f',
            'credits' => 50,
            'from_page' => 'account',
            'birthdate' => '2002-08-15'
        ]);

        $updatedCustomer = $customerMock->where('customer_id', $customerIdToSearch)->first();
        $extraData = new \stdClass();
        $extraData->value = 28;
        $extraData->comment = 'Repeat customer';
        $extraData->data = [];
        $extraData->rule_id = (isset($extraData->data['rule_id'])) ? $extraData->data['rule_id'] : -1;
        $extraData->module_on = "admin_adjusted";

        $this->assertEquals(1, $updatedCustomer['id']);
        $this->assertEquals('Vaibhav Rathod', $updatedCustomer['name']);
        $this->assertEquals($customerIdToSearch, $updatedCustomer['customer_id']);
        $event_name = 'send_customer_notification';
        $this->UploadAPI($updatedCustomer, $extraData, $event_name);
    }
}
