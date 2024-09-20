<?php

class ControllerApiSkSettings extends Controller
{

	public function index()
	{

		if (!isset($this->session->data['api_id'])) {
			$json['error'] = ['message' => 'Unauthorised'];
			$this->response->addHeader('Content-Type: application/json');

			$this->response->setOutput(json_encode($json));
			return true;
		}

		$this->load->model('catalog/sk_settings');
		$json = [];

		$json = $this->model_catalog_sk_settings->getAllSettings();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
