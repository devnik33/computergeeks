<?php

class ControllerApiSkProdu extends Controller
{
    // the price ids from the Plugin
    private $priceIds;

    public function index()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = ['message' => 'Unauthorised'];
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));
        } else {
            $this->load->language('api/cart');
            $this->load->model('catalog/product');
            $this->load->model('tool/image');
            $json = [];
            $json['products'] = [];
            $filter_data = [];
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
                $data['products'][] = [
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
                ];
            }
            $json['products'] = $data['products'];
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        }
    }

    public function test()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = ['message' => 'Unauthorised'];
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));

            return false;
        }

        $data = $this->request->get;

        $this->load->model('catalog/sk_product');
        $this->load->model('tool/image');

        $lastrun = str_replace('T', ' ', $data['lastrun']);
        $products = $this->model_catalog_sk_product->getProductsModifiedSince($lastrun);

        $results = [];
        foreach ($products as $product) {
            if (($result = $this->model_catalog_sk_product->getFullProduct($product['product_id'])) !== []) {
                array_push($results, $result);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($results));

        return;
        $results = [];
        for ($i = 400; $i < 450; ++$i) {
            if (($result = $this->model_catalog_sk_product->getFullProduct($i)) !== []) {
                array_push($results, $result);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($results));
    }

    public function getTable(){
        if (!$this->authCkeck()) {
            return false;
        }

        $table = $this->request->post['table'];
        
        $this->load->model('catalog/sk_product');

        $result = $this->model_catalog_sk_product->getTableData($table);


        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }
    
    public function syncExisting()
    {
        if (!$this->authCkeck()) {
            return false;
        }

        $products = $this->request->post['products'];
        $settings = $this->request->post['settings'];
        $fields = $this->request->post['fields'];

        $setting_id = $settings['barcodeonopencart'];
        $fromId = $settings['barcodeonsoftone'];
        
        $this->load->model('catalog/sk_product');

        $completed = [
            'updated' => [],
            'error' => [],
        ];
        $resultarray = [];
        foreach ($products as $product) {
            try {
                // get the current product
                if ($setting_id != $fromId){
                        $product[$setting_id] = $product[$fromId];
                    
                }
                $item = $this->model_catalog_sk_product->getProductByCode( $product[$setting_id], $setting_id );

                $result = $this->model_catalog_sk_product->fullUpdate($item['product_id'], $product, $fields);
                array_push($resultarray, $result);
                array_push($completed['updated'], $product[$setting_id]);
                
                if(isset($settings['extrafieldmod'])){
                    $this->model_catalog_sk_product->insertExtraField($product,$item['product_id'], $settings['extrafieldmod']);
                }
                
            } 
            catch (Exception $ex) {
                array_push($completed['error'], $product[$setting_id] . '--' . $ex->getMessage());
            }
        }


        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($completed));
    }

    public function sExisting()
    {
        if (!$this->authCkeck()) {
            return false;
        }

        $products = $this->request->post['products'];
        $settings = $this->request->post['settings'];
        $this->load->model('catalog/sk_product');

        foreach ($products as $product) {
            // get the current product
            $item = $this->model_catalog_sk_product->getProductByCode($product[$settings['barcodeonopencart']], $settings['barcodeonopencart']);

            $this->model_catalog_sk_product->fullUpdate($item['product_id'], $product);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['s' => 1]));
    }

    public function syncNew()
    {
        $errors = [];
        if (!$this->authCkeck()) {
            return false;
        }

        try {
            $products = $this->request->post['products'];
            $settings = $this->request->post['settings'];
            $fields = $this->request->post['fields'];
            $setting_id = $settings['barcodeonopencart'];
            $fromId = $settings['barcodeonsoftone'];
            
            $ret = [
                'alreadyExist' => [],
                'error' => [],
                'added' => [],
            ];
            $this->load->model('catalog/sk_product');

            $ids = [];
            foreach ($this->model_catalog_sk_product->getEans($setting_id) as $id) {
                array_push($ids, $id);
            }

            foreach ($products as $product) {
                //newHere///////////////////////
                if ($setting_id != $fromId){
                    if (isset($product[$setting_id]))
                        $product[$setting_id] = $product[$fromId];
                }
                if (in_array($product[$setting_id], $ids)) {
                    array_push($ret['alreadyExist'], $product[$setting_id]);

                    continue;
                }

                $result = $this->model_catalog_sk_product->addFullProduct($product, $fields);

                if (!isset($result['error']) || [] !== $result['error']) {
                    array_push($errors, $result);
                } else {
                    array_push($ret['added'], $result);
                    
                    //Extra Field Update
                    if(isset($settings['extrafieldmod']))
                    {
                         $this->model_catalog_sk_product->insertExtraField($product, $result['id'], $settings['extrafieldmod']);
                    }
            
                }
            }


        } 
        catch (Exception $ex) {
            $errors = $ex->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode([
            'success' => true,
            'results' => $ret,
            'errors' => $errors,
            'vars' => ini_get('max_input_vars'),
        ]));
    }



    /**
     * Get product List
     * expected query params:.
     *
     * @lastrun #retrieve products modified since lastrun
     * @fullProduct # retrieve all product details
     */
    public function list(){

        $final_result = ['products' => [], 'errors' => []];
        if(!$this->authCkeck()){
            $final_result['errors'][] = 'Failed Auth Check';

            $this->response->setOutput(json_encode($final_result));
            return;
        }
        try{
            $data = $this->request->get;
            $this->load->model('catalog/sk_product');
            $lastrun = str_replace('T', ' ', $data['lastrun']);
            $limit = isset($data['limit']) ? $data['limit'] : -1;
            $products = $this->model_catalog_sk_product->getProductsModifiedSince($lastrun, $limit);
        }
        catch(Exception $ex){
            $final_result['errors'][] = $ex->getLine() . $ex->getMessage();
        }
 
        foreach($products as $product){
            try{
                $result = $this->model_catalog_sk_product->getFullProduct($product['product_id']);
                if($result !== []){
                    array_push($final_result['products'], $result);
                }

            }
            catch(Exception $ex){
                array_push($final_result['errors'], '-pid-' . $product['product_id']);
            }
        }

        $this->response->setOutput(json_encode($final_result));
        return;


        // foreach ($products as $product) {
        //     try{
        //         $result = $this->model_catalog_sk_product->getFullProduct($product['product_id']);
        //         if($result !== []){
        //             array_push($final_result['products'], $result);
        //         }
        //     }
        //     catch(Exception $ex){
        //         array_push($final_result['errors'], $ex->getMessage() . $ex->getLine() . '|cd:'. $product->product_id .'|');
        //     }
        // }

        // $this->response->addHeader('Content-Type: application/json');
        // $this->response->setOutput($final_result);
        // return;
    }
    
    public function listConfigGroups()
    {
        if (!$this->authCkeck()) {
            return false;
        }
        $data = $this->request->get;
        $this->load->model('catalog/sk_product');
        $results = [];
        $results['category'] = $this->model_catalog_sk_product->getProductConfigCategories();
        $results['group'] = $this->model_catalog_sk_product->getProductConfigGroup();
        $results['product'] = $this->model_catalog_sk_product->getProductConfigProducts();
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($results));
    }

    /**
     * Get Customer Group Price List
     * expected query params:.
     *
     * @lastrun #retrieve products modified since lastrun
     * @fullProduct # retrieve all product details
     */
    public function groupPriceList()
    {
        if (!$this->authCkeck()) {
            return false;
        }

        $data = $this->request->get;

        $this->load->model('catalog/sk_product');
        $results = $this->model_catalog_sk_product->getGroupPriceList();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($results));
    }


    /**
     * getAllSku. Retrieves all products with the update information.
     */
    public function getAllSku()
    {
        $json = [];
        // check if user requesting is authenticated
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));

            return false;
        }

        // Load the model
        $this->load->model('catalog/sk_product');
        $productsInfo = $this->model_catalog_sk_product->getAllProductUpdateInfo();

        // return the data in json format
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($productsInfo));

        // getAllProductUpdateInfo
    }

    public function sp()
    {

        $json = [];

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));

            return '--';
        }

        //$this->load->model('catalog/product');
        $this->load->model('catalog/sk_product');
        $this->load->model('tool/image');

        $data = $this->request->post;

        $retList = [];
        $countAdd = 0;

        $this->priceIds = $this->model_catalog_sk_product->getProductPriceIds();
        $new = [];
        $updated = [];
        $rejected = [];
        $other = [];

        $myProducts = isset($data['products']) ? $data['products'] : [];

        $total = count($myProducts);

        foreach ($myProducts as $product) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($product));

            if (isset($product['barcode']) && strlen($product['barcode']) > 0 && isset($product['barcodeonopencart_field'])) {
                $item = $this->model_catalog_sk_product->getProductByCode($product['barcode'], $product['barcodeonopencart_field']);

                if (1 == $item->num_rows) {
                    // UPDATE
                    $pid = $this->model_catalog_sk_product->updateExisting($product, $this->priceIds);
                    array_push($updated, $pid . ':' . $product['barcode'] . ':' . $product['category_description_name']);
                } else {
                    //echo $item->num_rows.',';
                    //echo $product['barcode'];
                    $pid = $this->addSp($product);
                    if ('rejected:' == substr($pid, 0, 9)) {
                        array_push($rejected, $product['barcode'] . ':' . substr($pid, 9) . ' missing');
                    } else {
                        array_push($new, $product['barcode']);
                    }
                }
            } else {
                array_push($other, $product['category_description_name']);
            }
        }
        $json = [
            'new' => $new,
            'updated' => $updated,
            'rejected' => $rejected,
            'other' => $other,
            'sets' => $myProducts
        ];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function syncProductRelations()
    {
        $errors = [];
        if (!$this->authCkeck()) {
            return false;
        }

        try {
            $settings = $this->request->post['settings'];
            $relations = isset($this->request->post['relations']) ? $this->request->post['relations'] : [];
            $code_type = $settings['barcodeonopencart'];

            $this->load->model('catalog/sk_product');

            $result = $this->model_catalog_sk_product->saveProductRelations($relations, $code_type);

        } 
        catch (Exception $ex) {
            $errors = $ex->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode([
            'success' => $result,
            'error' => $errors,
        ]));
    }

    public function getEans()
    {
        if (!$this->authCkeck()) {
            return false;
        }
        
        $setting_id = $this->request->get["settingsBarcode"];

        $this->load->model('catalog/sk_product');
        
        $productCodes = $this->model_catalog_sk_product->getEans($setting_id);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($productCodes));
    }

    private function addSp($data)
    {

        $this->load->model('catalog/sk_product');
        // $this->model_catalog_tolis_product->addProduct($data,$this->priceIds);

        return $this->model_catalog_sk_product->addProduct($data, $this->priceIds);

        $postItems = [
            'product_description' => [
                '1' => [
                    'name' => $data['category_description_name'],
                    'description' => '',
                    'meta_title' => $data['category_description_name'],
                    'meta_description' => '',
                    'meta_keyword' => '',
                    'tag' => '',
                ],
            ],
            'model' => $data['model'],
            'sku' => $data['sku'],
            'upc' => '',
            'ean' => $data['sku'],
            'jan' => '',
            'isbn' => '',
            'mpn' => '',
            'location' => '',
            'price' => $data['price'],
            'tax_class_id' => $data['tax_class_id'],
            'quantity' => $data['quantity'],
            'minimum' => 1,
            'subtract' => 1,
            'stock_status_id' => $data['stock_status_id'],
            'shipping' => 1,
            'date_available' => date('Y-m-d'),
            'length' => '',
            'width' => '',
            'height' => '',
            'length_class_id' => $data['length_class_id'],
            'weight' => '',
            'weight_class_id' => 1,
            'status' => 1,
            'sort_order' => 1,
            'manufacturer' => '',
            'manufacturer_id' => 0,
            'category' => '',
            'product_category' => [
                $data->product_category,
            ],
            'filter' => '',
            'product_store' => [
                '0' => '0',
            ],
            'download' => '',
            'related' => '',
            'option' => '',
            'image' => 'catalog/profile-pic.png',
            'points' => '',
        ];

        $id = $this->model_catalog_sk_product->addProduct($postItems);
        //$id = $this->model_catalog_category->addCategory($postItems);

        //$this->response->addHeader('Content-Type: application/json');
        //$this->response->setOutput(json_encode(['success' => true, 'id' => $id]));
        return true;
    }
    
    public function syncProductConfig()
    {
        if (!$this->authCkeck()) {
            return false;
        }

        $products = $this->request->post['products'];
        $categories = $this->request->post['categories'];
        $groups = $this->request->post['groups'];
        
        $settings = $this->request->post['settings'];
        $setting_id = $settings['barcodeonopencart'];
        $fromId = $settings['barcodeonsoftone'];
        
        $this->load->model('catalog/sk_product');

        $categoryMappings = $this->model_catalog_sk_product->addProductConfiguratorCategories($categories, $setting_id);
        $test1 = $this->model_catalog_sk_product->addProductConfiguratorGroups($groups);
        $test3 = $this->model_catalog_sk_product->addProductConfiguratorProducts($products, $setting_id, $fromId, $categoryMappings);     
        

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode([$test1,$categoryMappings, $test3]));
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

        //V2-Specific Functions
        public function getProductIDs(){
            $result = ['ids' => [], 'errors' => []];
    
            if(!$this->authenticated()){
                $result['errors'][] = 'Failed Auth Check';
                $this->response->setOutput(json_encode($result));
                return;
            }
    
            try{
                $this->load->model('catalog/sk_product');
                $product_ids = $this->model_catalog_sk_product->getProductIds();
                $result['ids'] = $product_ids;
            }
            catch(Exception $ex){
                $result['errors'][] = $ex->getLine() . $ex->getMessage();
            }
     
    
            $this->response->setOutput(json_encode($result));
            return;
        }

        public function getProductsFromIDs(){
            $result = ['products' => [], 'errors' => []];
    
            if(!$this->authenticated()){
                $result['errors'][] = 'Failed Auth Check';
                $this->response->setOutput(json_encode($result));
                return;
            }

            try{
                $this->load->model('catalog/sk_product');
                $product_ids = $this->request->post['product_ids'];

                $products = $this->model_catalog_sk_product->getProductsFromIDs($product_ids);
                $result['products'] = $products;
            }
            catch(Exception $ex){
                $result['errors'][] = $ex->getLine() . $ex->getMessage();
            }

            $this->response->setOutput(json_encode($result));
            return;
        }
    
        private function authenticated(){
            if (!isset($this->session->data['api_id'])){
                return false;
            }
    
            return true;
        }
}