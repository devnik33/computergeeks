<?php

class ControllerApiSkCategory extends Controller
{
    public function index()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));
        } else {
            $this->load->language('api/cart');
            $this->load->model('catalog/sk_category');

            $json = [];
            $json['categories'] = [];
            

            $results = $this->model_catalog_sk_category->getShistos();

            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode(['categories' => $results]));
        }
    }

    public function add()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));
        } 
        else {
            $this->load->model('catalog/sk_category');
            $data = $this->request->post;
            
            //Getting Category Name
            $cname = $data['category_description_name'];
            
            //Getting Parent Category id on target opencart (Here), 0 if has none
            $mainCategoryMapping = $data['mainCategoryMap'];
            
                //parent_id
                $p_id = 0;
                
                //If there is a parent category (found by name and "--" then get its id)
                if (($pos = strpos($cname, "--")) !== FALSE) { 
                    $mainCategoryName = strtok($cname, '-');
                    $data['category_description_name'] = substr($cname, $pos+3);
                    $mainCatExists = $this->model_catalog_sk_category->getCategoryByName($mainCategoryName);
                    //If Parent category is found
                    if(count($mainCatExists) > 0)
                    {
                        //Change p_id to found parent category id
                        $p_id = $mainCatExists['category_id'];
                    }
                    $mainCatExistsOverride = $this->model_catalog_sk_category->getCategory($mainCategoryMapping);
                    if(count($mainCatExistsOverride) > 0)
                    {
                        //Overrides p_id if main category is mapped to a different category.
                        //Ex.: Ram is mapped to "Memory" instead of Ram.
                        // Ram -> DDR4 should be overriden to Memory-> DDR4.
                        $p_id = $mainCatExistsOverride['category_id'];
                    }
                }
    

            //Check if category already exists.
            //$categoryExists = $this->model_catalog_sk_category->getCategoryWithParent($data['category_description_name']);
            $categoryExists = $this->model_catalog_sk_category->getCategoryByName($data['category_description_name']);
            $categoryExistsWithParent = $this->model_catalog_sk_category->getCategoryWithParent($data['category_description_name'], $p_id);
            if (count($categoryExists) > 0 and ($categoryExists['parent_id'] == 0 or (count($categoryExistsWithParent) > 0) ) )
            {
                if(count($categoryExistsWithParent) == 0)
                {
                    $this->model_catalog_sk_category->updateParentId($categoryExists, $p_id);
                }
                
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(['success' => false, 'message' => 'category already exists', 'id' => $categoryExists['category_id'], '1' => $p_id, '2' => $categoryExists]));   
            }
            else {

                $postItems = [
                    'category_description' => [
                        '1' => [
                            'name' => $data['category_description_name'],
                            'description' => '',
                            'meta_title' => $data['category_description_name'],
                            'meta_description' => '',
                            'meta_keyword' => '',
                        ],
                    ],
                    'path' => '',
                    'parent_id' => $p_id,
                    'filter' => '',
                    'category_store' => ['0'],
    
                    'image' => $data['category_image'],
                    'imageUrl' => $data['category_imageUrl'],
                    'column' => '1',
                    'sort_order' => '0',
                    'status' => '1',
                    'category_seo_url' => [
                        '0' => [
                            '1' => '',
                        ],
                    ],
                    'category_layout' => ['0' => ''],
                ];
    
                $id = $this->model_catalog_sk_category->addCategory($postItems);
    
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(['success' => true, 'message' => 'Category was successfully added', 'ids' => $id, 'data' => $categoryExists]));
            }
        }
    }
    
    //This function is for adding all categories at once. Same Logic as (add), just receives data for multiple categories.
    public function add2()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        } else {
            $this->load->model('catalog/sk_category');
            $datas = $this->request->post;
            $ids = [];
            foreach ($datas['categories'] as $data)
            {
                //Getting Category Name
                $cname = html_entity_decode(ucfirst($data['name']));
                $data['category_description_name'] = html_entity_decode(ucfirst($data['name']));
                $mainCategoryMapping = html_entity_decode(ucfirst($data['mainCategoryMap']));

                    $p_id = 0;
                    
                    //If there is a parent category (found by name and "--" then get its id)
                    if (($pos = strpos($cname, "--")) !== FALSE) { 
                        $mainCategoryName = strtok($cname, '-');
                        $data['category_description_name'] = substr($cname, $pos+3);
                        $mainCatExists = $this->model_catalog_sk_category->getCategoryByName($mainCategoryName);
                        //If Parent category is found
                        if(count($mainCatExists) > 0)
                        {
                            //Change p_id to found parent category id
                            $p_id = $mainCatExists['category_id'];
                        }
                        $mainCatExistsOverride = $this->model_catalog_sk_category->getCategory($mainCategoryMapping);
                        if(count($mainCatExistsOverride) > 0)
                        {
                            //Overrides p_id if main category is mapped to a different category.
                            //Ex.: Ram is mapped to "Memory" instead of Ram.
                            // Ram -> DDR4 should be overriden to Memory-> DDR4.
                            $p_id = $mainCatExistsOverride['category_id'];
                        }
                    }
        

                //Check if category already exists.
                //$categoryExists = $this->model_catalog_sk_category->getCategoryWithParent($data['category_description_name']);
                $categoryExists = $this->model_catalog_sk_category->getCategoryByName($data['category_description_name']);
                $categoryExistsWithParent = $this->model_catalog_sk_category->getCategoryWithParent($data['category_description_name'], $p_id);
                if (count($categoryExists) > 0 and ($categoryExists['parent_id'] == 0 or (count($categoryExistsWithParent) > 0) ) )
                {
                    if(count($categoryExistsWithParent) == 0)
                    {
                        $this->model_catalog_sk_category->updateParentId($categoryExists, $p_id);
                    } 
                    $id = [];
                    $id['target_id'] = $categoryExists['category_id'];
                    $id['source_id'] = $data['category_id'];
                    array_push($ids,$id);
                }
                else {

                    $postItems = [
                        'category_description' => [
                            '1' => [
                                'name' => $data['category_description_name'],
                                'description' => '',
                                'meta_title' => $data['category_description_name'],
                                'meta_description' => '',
                                'meta_keyword' => '',
                            ],
                        ],
                        'path' => '',
                        'parent_id' => $p_id,
                        'filter' => '',
                        'category_store' => ['0'],
        
                        'image' => $data['image'],
                        'imageUrl' => $data['category_imageUrl'],
                        'column' => '1',
                        'sort_order' => '0',
                        'status' => '1',
                        'category_seo_url' => [
                            '0' => [
                                '1' => '',
                            ],
                        ],
                        'category_layout' => ['0' => ''],
                    ];
                    $id = [];
                    $id['target_id'] = $this->model_catalog_sk_category->addCategory($postItems);
                    $id['source_id'] = $data['category_id'];
                    array_push($ids,$id);
                }
            }
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['success' => true, 'message' => 'Categories was successfully added', 'ids' => $ids]));
        } 
    }

    public function getFullCategory()
    {
        if (!$this->authCkeck()) {
            return false;
        }

        $this->load->model('catalog/sk_category');

        $categoryInfo = $this->model_catalog_sk_category->getFullCategory();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($categoryInfo));
    }

    public function getConfigurator()
    {
        if (!$this->authCkeck()) {
            return false;
        }

        $this->load->model('catalog/sk_category');

        $categoryInfo = $this->model_catalog_sk_category->getProductConfiguratorData();
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($categoryInfo));
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

    public function synchronize(){
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        
        $this->load->model('catalog/sk_category');
        $data = $this->request->post;
        $categories = $data['categories'];

        $results = [];
        foreach($categories as $category){
            $results[] = $this->model_catalog_sk_category->synchronizeCategory($category);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['result' => $results, 'payload' => $data])); 
    }

    public function addNew()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));
        } else {
            $this->load->model('catalog/sk_category');
            $data = $this->request->post;
            
            //Getting Category Name
            
            $cname = $data['category_description_name'];
            
            //Getting Parent Category id on target opencart (Here)
            
                //parent_id
                $p_id = 0;
                
                //If there is a parent category (found by name and "--" then get its id)
                if (($pos = strpos($cname, "--")) !== FALSE) { 


                    $category_path = explode( ' -- ', $cname);
                    $category_length = count($category_path);
                    $category_levels = [];
                    $count = 0;
                    foreach($category_path as $current_category)
                    {   
                        $count = $count + 1;
                        $category_exists = $this->model_catalog_sk_category->getCategoryByName($current_category);
                        if(count($category_exists) > 0)
                        {
                            $id = $category_exists['category_id'];
                            $parent_id = $category_exists['parent_id'];
                        }
                    }
                    $data['category_description_name'] = substr($cname, $pos+3);
                    $mainCatExists = $this->model_catalog_sk_category->getCategoryByName($mainCategoryName);
                    //If Parent category is found
                    if(count($mainCatExists) > 0)
                    {
                        //Change p_id to found parent category id
                        $p_id = $mainCatExists['category_id'];
                    }
                    $mainCatExistsOverride = $this->model_catalog_sk_category->getCategory($mainCategoryMapping);
                    if(count($mainCatExistsOverride) > 0)
                    {
                        //Overrides p_id if main category is mapped to a different category.
                        //Ex.: Ram is mapped to "Memory" instead of Ram.
                        // Ram -> DDR4 should be overriden to Memory-> DDR4.
                        $p_id = $mainCatExistsOverride['category_id'];
                    }
                }
    

            //Check if category already exists.
            //$categoryExists = $this->model_catalog_sk_category->getCategoryWithParent($data['category_description_name']);
            $categoryExists = $this->model_catalog_sk_category->getCategoryByName($data['category_description_name']);
            $categoryExistsWithParent = $this->model_catalog_sk_category->getCategoryWithParent($data['category_description_name'], $p_id);
            if (count($categoryExists) > 0 and ($categoryExists['parent_id'] == 0 or (count($categoryExistsWithParent) > 0) ) )
            {
                if(count($categoryExistsWithParent) == 0)
                {
                    $this->model_catalog_sk_category->updateParentId($categoryExists, $p_id);
                }
                
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(['success' => false, 'message' => 'category already exists', 'id' => $categoryExists['category_id'], '1' => $p_id, '2' => $categoryExists]));   
            }
            else {

                $postItems = [
                    'category_description' => [
                        '1' => [
                            'name' => $data['category_description_name'],
                            'description' => '',
                            'meta_title' => $data['category_description_name'],
                            'meta_description' => '',
                            'meta_keyword' => '',
                        ],
                    ],
                    'path' => '',
                    'parent_id' => $p_id,
                    'filter' => '',
                    'category_store' => ['0'],
    
                    'image' => $data['category_image'],
                    'imageUrl' => $data['category_imageUrl'],
                    'column' => '1',
                    'sort_order' => '0',
                    'status' => '1',
                    'category_seo_url' => [
                        '0' => [
                            '1' => '',
                        ],
                    ],
                    'category_layout' => ['0' => ''],
                ];
    
                $id = $this->model_catalog_sk_category->addCategory($postItems);
    
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(['success' => true, 'message' => 'Category was successfully added', 'ids' => $id, 'data' => $categoryExists]));
            }
        }
    }











    public function addNewCategory(){


        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));
            return;
        } 
        else {
            $results = ['new' => [], 'old' => [], 'errors' => []];
            $this->load->model('catalog/sk_category');
            $data = $this->request->post;
            $mappings = $data['source_ids_to_target_ids'];
            $verified_mappings = [];
            $existing = [];

            foreach($data['categories'] as $category){
                try{
                    if($category['parent_id'] == 0){
                        $parent_id = 0;
                    }
                    else{
                        $parent_id = $verified_mappings[$category['parent_id']];
                        $category['parent_id'] = $verified_mappings[$category['parent_id']];
                    }
                    if(isset($mappings[$category['category_id']]) && $mappings[$category['category_id']] != 0){
                        $cat = $this->model_catalog_sk_category->verifyCategoryExists($mappings[$category['category_id']], $parent_id);
                        array_push($existing, ['cat' => $cat, 'what' => '1', 'sent' => $mappings[$category['category_id']], 'p' => $parent_id]);
                    }
                    else{
                        $cat = $this->model_catalog_sk_category->getCategoryByName2($category['category_name'], $parent_id);
                        array_push($existing, ['cat' => $cat, 'what' => '2', 'sent' => $category, 'p' => $parent_id]);
                    }
                    

                    if(!empty($cat)){
                        $verified_mappings[$category['category_id']] = $cat['category_id'];
                        array_push($results['old'], $cat);
                    }
                    else{
                        $new_category_id = $this->model_catalog_sk_category->addFullCategoryNew($category, $verified_mappings);
                        $verified_mappings[$category['category_id']] = $new_category_id;
                        array_push($results['new'], $new_category_id);
                    }
                }
                catch(Exception $ex){
                    array_push($results['errors'], $ex->getMessage() . $ex->getLine());
                }
            }

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(
                json_encode(
                    ['success' => true, 'results' => $results, 'verified' => $verified_mappings, 'existing' => $existing]
                )
            );   
            return;


        }
    }
}