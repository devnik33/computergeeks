<?php
class ControllerApiTolisTest extends Controller
{	
	// the price ids from the Plugin
	private $priceIds;
	
    public function index()
    {
	
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(['hello' => 'worlds']));
	
	}

	public function products(){
		if (!isset($this->session->data['api_id'])) {
			$json['error'] = ['message' => 'Unauthorised'];
			$this->response->addHeader('Content-Type: application/json');
			
			$this->response->setOutput(json_encode($json));
			
		}
		else{
		
			$this->load->language('api/cart');
			$this->load->model('catalog/product');
			$this->load->model('tool/image');
			$json = array();
			$json['products'] = array();
			$filter_data = array();
			$results = $this->model_catalog_product->getProducts($filter_data);
					$this->response->addHeader('Content-Type: application/json');

		$this->response->setOutput(json_encode(['hello' => $results]));


		}
		
	}
	/* 
	public function add() {

		$this->load->model('catalog/product');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

			$this->model_catalog_product->addProduct($this->request->post);
			
			$data = $this->request->post;
			var_dump($data);
			die();
			$postItems = [
				"product_description" => [
					"1" => [
						"name" => $data['category_description_name'],
						"description" => "",
						"meta_title" => $data['category_description_name'],
						"meta_description" => "",
						"meta_keyword" => "",
						"tag" => ""
					]
				],
				"model" => $data['model'],
				"sku" => isset($data['sku']) ? $data['sku'] : '',
				"upc" => "",
				"ean" => isset($data['sku']) ? $data['sku'] : '',
				"jan" => "",
				"isbn" => "",
				"mpn" => "",
				"location" => "",
				"price" => $data['price'],
				"tax_class_id" => $data['tax_class_id'],
				"quantity" => $data['quantity'],
				"minimum" => 1,
				"subtract" => 1,
				"stock_status_id" => $data['stock_status_id'],
				"shipping" => 1,
				"date_available" => date('Y-m-d'),
				"length" => "",
				"width" => "",
				"height" => "",
				"length_class_id" => $data['length_class_id'],
				"weight" => '',
				"weight_class_id" => 1,
				"status" => 1,
				"sort_order" => 1,
				"manufacturer" => "",
				"manufacturer_id" => 0,
				"category" => "",
				"product_category" => [
					$data->product_category
				],
				"filter" => "",
				"product_store" => Array
					(
						"0" => "0"
					),
				"download" => "",
				"related" => "",
				"option" => "",
				"image" => "catalog/profile-pic.png",
				"points" => ""
			];
			
			//$id = $this->model_catalog_category->addCategory($postItems);
		
			//$this->response->addHeader('Content-Type: application/json');
			//$this->response->setOutput(json_encode(['success' => true, 'id' => $id]));
			var_dump($postItems);

		}

	}
	*/

}