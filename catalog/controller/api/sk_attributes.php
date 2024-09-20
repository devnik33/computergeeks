<?php

class ControllerApiSkAttributes extends Controller
{
    public function index()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));

            return false;
        }

        $this->response->addHeader('Content-Type: application/json');

        $this->load->model('catalog/sk_attributes');

        $results = $this->model_catalog_sk_attributes->getAttributes();
        $this->response->setOutput(json_encode($results));
    }

	public function syncFilters(){
		if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));

            return false;
        }
		
		$this->response->addHeader('Content-Type: application/json');
		$this->load->model('catalog/sk_attributes');
		$result = $this->model_catalog_sk_attributes->syncFilters();
		
		$this->response->setOutput(json_encode($result));
	}
}
