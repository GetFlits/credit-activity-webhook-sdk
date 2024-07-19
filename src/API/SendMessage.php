<?php

namespace Flits\CreditActivityWebhook\API;

use Flits\CreditActivityWebhook\CreditActivityWebhookProvider;

class SendWebhook extends CreditActivityWebhookProvider {

    function __construct($config) {
        parent::__construct($config);
    }
}
