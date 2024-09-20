<?php

class ControllerApiSkCustomers extends Controller
{
    public function index()
    {
        $this->response->addHeader('Content-Type: application/json');

        if (!isset($this->session->data['api_id'])) {
            $json['success'] = false;
			$json['error'] = 'Unauthorized';
			$json['errorcode']=-1;
            
			$this->response->setOutput(json_encode($json));
        
		} else {

	        $this->load->model('catalog/sk_customer');
			$existingCustomerIds = $this->getExistingCustomerIds();
            $customers = $this->request->post['customers'];
			$mappedIds = [];
			foreach($customers as $customer){
				// if customer exists update
				
				if(array_key_exists($customer['source_id'],$existingCustomerIds)){
					$this->model_catalog_sk_customer->editCustomer($existingCustomerIds[$customer['source_id']], $customer);
					$mappedIds[$customer['source_id']] = $existingCustomerIds[$customer['source_id']];					
				} else {
					$id = $this->model_catalog_sk_customer->addCustomer($customer);
				}
				
				
			}
						
			$this->response->setOutput(json_encode(['success'=>true,'customers'=>$customers, 'ids'=>$mappedIds ]) );
        }
    }
	

	private function getExistingCustomerIds(){
	    $this->load->model('catalog/sk_customer');
		$customers = $this->model_catalog_sk_customer->getExistingCustomerIds();
		$result = [];
		foreach($customers as $customer) {
			$result[$customer['source_id']] = $customer['customer_id'];
		}
		return $result;
	}
	
	
	public function init(){
	    $this->load->model('catalog/sk_customer');
		$this->model_catalog_sk_customer->initTables();
		
		$this->response->setOutput(json_encode(['success'=>true]) );
	}
	
}
