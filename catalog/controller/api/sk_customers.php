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
			return;
		} 

		$this->load->model('catalog/sk_customer');

		$customers = $this->request->post['customers'];
		$source_update_fields = $this->request->post['customer_update_fields'];
		$map_field = $this->request->post['customer_map_field']; // email | phone

		$all_fields = ['firstname', 'lastname', 'email', 'address', 'telephone', 'status'];
		$customer_update_fields = empty($source_update_fields) ? $all_fields : array_intersect($all_fields, $source_update_fields);

		$existing_customers = $this->getExistingCustomers($map_field);
		
		$results = ['result' => [], 'errors' => []];
		$missing_count = 0;

		foreach($customers as $customer){
			$customer = (array) $customer;

			if(empty($customer[$map_field])){
				$missing_count += 1;
				$results['errors']['missing'] = 'Customers missing [' . $map_field . ']' . ' field: ' . $missing_count;
				continue;
			}

			$index = $customer[$map_field];


			if(empty( $existing_customers[$index] )){
				$result['result'] = $this->model_catalog_sk_customer->addCustomer($customer);
				$result['function'] = 'Add new Customer';
			}
			else{
				$existing_customer_id = $existing_customers[$index];
				$result['result'] = $this->model_catalog_sk_customer->editCustomer($customer, $customer_update_fields, $map_field, $existing_customer_id);
				$result['function'] = 'Edit Customer';
			}

			$results['result'][] = $result;
		}

		
		$this->response->setOutput(json_encode([
			'success' => true,
			'customers' => $customers,
			'results' => $results 
		]));
    }

	private function getExistingCustomers($map_field){
		$this->load->model('catalog/sk_customer');
		$query_rows = $this->model_catalog_sk_customer->getExistingCustomers();

		$result = [];
		foreach($query_rows as $db_customer){
			$map_index = $db_customer[$map_field];
			$result[$map_index] = $db_customer['customer_id'];
		}

		return $result;
	}



	public function getCustomers(){
        $this->response->addHeader('Content-Type: application/json');

        if (!isset($this->session->data['api_id'])) {
            $json['success'] = false;
			$json['error'] = 'Unauthorized';
			$json['errorcode']=-1;
            
			$this->response->setOutput(json_encode($json));
			return;
		} 

		$this->load->model('catalog/sk_customer');
		$results = ['result' => [], 'errors' => []];

		
		$results['result'] = $this->model_catalog_sk_customer->getAllCustomers();
		
		$this->response->setOutput(json_encode([
			'success' => true,
			'results' => $results 
		]));
    }
}
