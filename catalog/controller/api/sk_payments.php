<?php

class ControllerApiSkPayments extends Controller
{
    public function paymentExtensions(){
        if (!$this->authCkeck()) {
            return false;
        }

        $this->load->model('catalog/sk_payments');

        $paymentExtensions = $this->model_catalog_sk_payments->getPaymentExtensions();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($paymentExtensions));
    }

    /**
     * orderStatuses - Retrieves all the order Statuses from the database
     * @return json array
     */

    public function orderStatuses()
    {
        $this->load->model('catalog/sk_payments');

        $paymentExtensions = $this->model_catalog_sk_payments->getOrderStatuses();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($paymentExtensions));

    }
    
    private function authCkeck()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = ['message' => 'Unauthorised'];
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));

            return false;
        }

        return true;
    }

    
}