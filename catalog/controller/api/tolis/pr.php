<?php
class ControllerApiProduct extends Controller
{	
	// the price ids from the Plugin
	private $priceIds;
	
    public function index()
    {
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
		
        foreach ($results as $result) {
            if ($result['image']) {
                $image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
            }
            if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                $price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
            } else {
                $price = false;
            }
            if ((float) $result['special']) {
                $special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
            } else {
                $special = false;
            }
            if ($this->config->get('config_tax')) {
                $tax = $this->currency->format((float) $result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
            } else {
                $tax = false;
            }
            if ($this->config->get('config_review_status')) {
                $rating = (int) $result['rating'];
            } else {
                $rating = false;
            }
            $data['products'][] = array(
                'product_id' => $result['product_id'],
                'thumb' => $image,
                'name' => $result['name'],
                'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
                'price' => $price,
                'special' => $special,
                'tax' => $tax,
                'minimum' => $result['minimum'] > 0 ? $result['minimum'] : 1,
                'rating' => $result['rating'],
                'href' => $this->url->link('product/product', 'product_id=' . $result['product_id']),
				'ean' => $result['ean'],
            );
		}
			$json['products'] = $data['products'];
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
		}
			
	}
	
	public function products(){
	
		
		return 'ssss;l';
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

	public function sp()
	{
		$json = [];

		if (!isset($this->session->data['api_id'])) 
		{
			$json['error'] = 'Unauthorized';
			$this->response->addHeader('Content-Type: application/json');
			
			$this->response->setOutput(json_encode($json));
			return '--';
			
		}		
		//$this->load->model('catalog/product');
		$this->load->model('catalog/tolis/product');		
        $this->load->model('tool/image');
        
        $data = $this->request->post;
		
		$retList = [];
		$countAdd =0;

		$this->priceIds = $this->model_catalog_tolis_product->getProductPriceIds();
		$new = [];
		$updated =  [];
		$rejected = [];
		
		foreach($data['products'] as $product)
		{			
			if( isset($product['barcode']) && strlen($product['barcode']) >0 && isset($product['barcodeonopencart_field']) )
			{
				
				$item = $this->model_catalog_tolis_product->getProductByCode($product['barcode'], $product['barcodeonopencart_field']);
				
				if($item->num_rows == 1)
				{
					// UPDATE
					$this->model_catalog_tolis_product->updateExisting($product,$this->priceIds);
					array_push($updated, $product['barcode']);
				}
				else{			
					//echo $item->num_rows.',';	
					//echo $product['barcode'];
					 $this->addSp($product);
					 array_push($new, $product['barcode']);
				}
				
			}
			else{
				
				
			}
	
		}
		
		$json = [
			'new' => $new,
			'updated' => $updated,
			'rejected' => $rejected,
		];
			
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
		
	}
	
	private function addSp($data)
	{
			$this->load->model('catalog/tolis/product');
			// $this->model_catalog_tolis_product->addProduct($data,$this->priceIds);
			
			return $this->model_catalog_tolis_product->addProduct($data,$this->priceIds);;
		
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
				"sku" => $data['sku'],
				"upc" => "",
				"ean" => $data['sku'],
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
			
			$id =$this->model_catalog_tolis_product->addProduct($postItems);
			//$id = $this->model_catalog_category->addCategory($postItems);
		
			//$this->response->addHeader('Content-Type: application/json');
			//$this->response->setOutput(json_encode(['success' => true, 'id' => $id]));
			return true;


	}
}