<?php

class ModelCatalogSkProduct extends Model
{
    public function getProductByCode($code, $field)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product p WHERE ' . $field . " = '" . $code . "' ORDER BY date_modified");
        $products = $query->rows;
        $product_count = count( (array) $products);



        if($product_count > 1){
            $last_element = array_pop($products); 
            foreach($products as $product){
                if(!empty($product['product_id'])){
                    $this->db->query('DELETE FROM ' . DB_PREFIX . 'product WHERE product_id = ' . (int) $product['product_id']);
                }
            }

            $result = $last_element;
        }
        else{
            $result = $query->row;
        }
        return $result;
    }

    public function getProductIDByCode($code, $field)
    {
        $query = $this->db->query('SELECT product_id FROM ' . DB_PREFIX . 'product p WHERE ' . $field . " = '" . $code . "' ORDER BY product_id LIMIT 1");
        return $query->row;
    }

    public function getAllProductUpdateInfo()
    {
        $query = $this->db->query('
			SELECT 
				p.product_id,
				p.ean,
				p.price,
				p.quantity,
				p.manufacturer_id,
				max( case when pp.customer_group_id=1 then pp.price end ) as price1,
				max( case when pp.customer_group_id=2 then pp.price end ) as price2
				
			FROM ' . DB_PREFIX . 'product p
			
			LEFT JOIN ' . DB_PREFIX . 'product_price pp USING (product_id)
			GROUP BY product_id
		');

        return $query->rows;
    }

    public function getProductPriceIds()
    {
        $query = $this->db->query("
				SELECT 
					sum( IF(cgd.name='Wholesale', cg.customer_group_id, 0) ) AS Wholesale,
					sum( IF(cgd.name='Retail', cg.customer_group_id, 0) ) AS Retail	
					FROM " . DB_PREFIX . 'customer_group cg

					INNER JOIN ' . DB_PREFIX . 'customer_group_description cgd 
						USING(customer_group_id)

					INNER JOIN ' . DB_PREFIX . "language l ON l.language_id = cgd.language_id
					WHERE l.code='en-gb' limit 1
			");

        return $query->row;
    }

    public function getGroupPriceList()
    {
        $query = $this->db->query("
				SELECT 
                    customer_group_id
                    ,cgd.name
					FROM " . DB_PREFIX . 'customer_group cg

					INNER JOIN ' . DB_PREFIX . 'customer_group_description cgd 
						USING(customer_group_id)

					INNER JOIN ' . DB_PREFIX . "language l ON l.language_id = cgd.language_id
					WHERE l.code='en-gb' 
                    
			");

        return $query->rows;
    }

    public function getTableData($table){
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . $table);
        return $query->rows;
    }
    
    public function addManufacturer($name)
    {
        $this->db->query('INSERT INTO ' . DB_PREFIX . "manufacturer SET name = '" . $this->db->escape($name) . "', sort_order = '0'");

        $manufacturer_id = $this->db->getLastId();

        $this->db->query('INSERT INTO ' . DB_PREFIX . "manufacturer_to_store SET manufacturer_id = '" . (int) $manufacturer_id . "', store_id = '0'");

        $this->cache->delete('manufacturer');

        return $manufacturer_id;
    }

    public function saveProductRelations($relations, $code_type){
        $saved_relations = ['No Relations Found'];
        if(empty($relations)){
            return ['No Relations Found'];
        }

        $saved_relations = ['source' => ['relations' => $relations, 'code' => $code_type], 'target' => []];
        
        foreach($relations as $relation_key => $relation_array){
            foreach($relation_array as $relation_data){
                if($relation_data['product'] == $relation_data['relation_product']){
                    continue;
                }

                $source_product = $this->getProductIDByCode( $relation_data['product'], $code_type );
                $target_product = $this->getProductIDByCode( $relation_data['related_product'], $code_type );

                if(empty($source_product) || empty($target_product)){
                    continue;
                }

                $product_id = $source_product['product_id'];
                $related_id = $target_product['product_id'];

                if($related_id == $product_id){
                    continue;
                }

                $saved_relations['target'][] = ['product_id' => $product_id, 'relation_id' => $related_id];
        
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $product_id . "' AND related_id = '" . (int) $related_id . "'");
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $related_id . "' AND related_id = '" . (int) $product_id . "'");
        
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_related SET product_id = '" . (int) $product_id . "', related_id = '" . (int) $related_id . "'");
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_related SET product_id = '" . (int) $related_id . "', related_id = '" . (int) $product_id . "'");
            }
        }

        return $saved_relations;
    }

    public function fullUpdate($product_id, $data, $fields)
    {
        $language_id = (int) $this->config->get('config_language_id');
        $sort_order = 0;
        
        $productTableFields = [
            'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'location',
            'quantity', 'minimum', 'subtract', 'stock_status_id', 'date_available', 'manufacturer_id', 'shipping',
            'price', 'points', 'weight', 'weight_class_id', 'length', 'width', 'height', 'length_class_id', 'status',
            'tax_class_id', 'sort_order',
        ];

        $sql = 'UPDATE ' . DB_PREFIX . 'product SET ';

        foreach ($data as $k => $v) {
            if (in_array($k, $productTableFields)) {
                $sql .= ' ' . $k . " = '" . $this->db->escape($v) . "',
            ";
            }
        }

        $sql .= "date_modified = NOW() 
            WHERE product_id='" . $this->db->escape($product_id) . "'  
        ";

        $this->db->query($sql);

        if (isset($data['image'])) {
            $query = $this->db->query('SELECT image FROM ' . DB_PREFIX . "product WHERE product_id = '". (int) $product_id ."'");
            foreach ($query->rows as $qry){
                $img = $qry['image'];
            }
            
            $imgToCompare = $data['image'];
            $imgToCompare = trim(substr($imgToCompare, strrpos($imgToCompare, '/') + 1));
            $imgToCompare =  str_replace(trim(substr($imgToCompare, strrpos($imgToCompare, '.'))), '', $imgToCompare);
            
            if((strpos(trim($img), trim($imgToCompare)) == false) or ($data['imageUpdate'] == 1)){

                $postfix = '';
                $extension = '';
                $is_webp = false;

                $image_filetype = substr($data['image'], -5);

                $content_image = str_replace(' ', '%20', $data['image']);
                $content_image_url = $data['imageUrl'];

                $image_to_update = $data['image'];
                $image_to_update = $this->addCatalogToImage($image_to_update);

                if(! (strpos($image_filetype, '.') !== false) ){
                    $extension = '?output=jpeg';
                    $postfix = '.jpg';
                }

                if($image_filetype == '.webp'){
                    $postfix = '.jpg';
                    $extension = '?output=jpeg';
                    $image_to_update = substr($image_to_update, 0, -5);
                    $is_webp = true;
                }


                $content = file_get_contents($content_image_url . '/' . $content_image . $extension);
                
                $this->db->query('UPDATE ' . DB_PREFIX . "product SET image = '" .
                $this->db->escape($image_to_update) . $postfix ."' WHERE product_id = '" . (int) $product_id . "'");
                
                if (!is_dir(DIR_IMAGE . '/' . dirname($image_to_update))) {
                    mkdir(DIR_IMAGE . '/' . dirname($image_to_update), 0777, true);
                }

                $result['img'] = $this->saveImageToDir($content, $image_to_update . $postfix, $is_webp);
            }
        }


        if(isset($data['additional_images'])){
            $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_image WHERE product_id = '". (int) $product_id ."'");

            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_image WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['additional_images'] as $additional_image){

                $image_exists = false;

                if($data['imageUpdate'] == 1){
                    $image_exists = true;
                }
                else{
                    foreach($query->rows as $db_product_additional_image){
                        $compare_image = $additional_image['image'];
                        $db_additional_image = $db_product_additional_image['image'];
    
                        //Removing Postfix and keeping only the last path to compare.
                        $compare_image = trim(substr($compare_image, strrpos($compare_image, '/') + 1));
                        $compare_image =  str_replace(trim(substr($compare_image, strrpos($compare_image, '.'))), '', $compare_image);
    
                        if(strpos(trim($db_additional_image), trim($compare_image)) == true){
                            $image_exists = true;
                        }
                    }
                }


                $postfix = '';
                $extension = '';
                $is_webp = false;

                $image_filetype = substr($additional_image['image'], -5);

                $content_image = str_replace(' ', '%20', $additional_image['image']);

                $image_to_update = $additional_image['image'];
                $image_to_update = $this->addCatalogToImage($image_to_update);

                if(! (strpos($image_filetype, '.') !== false) ){
                    $extension = '?output=jpeg';
                    $postfix = '.jpg';
                }

                if($image_filetype == '.webp'){
                    $postfix = '.jpg';
                    $extension = '?output=jpeg';
                    $image_to_update = substr($image_to_update, 0, -5);
                    $is_webp = true;
                }

                $image_dir = '';
                if(!empty($additional_image['base_image_url'])){
                    $image_dir = $additional_image['base_image_url'];
                }
                else{
                    $image_dir = $data['imageUrl'];
                }

                $image_content_directory = $image_dir . '/' . $content_image . $extension;


                $sanitized_url = preg_replace('/[^a-zA-Z0-9\-\._]/', '', basename($image_to_update));
                $new_filename = dirname($image_to_update) . '/' . $sanitized_url . $postfix;


                
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_image SET product_id = '" . (int) $product_id . "', image = '" . $this->db->escape($new_filename) .  "', sort_order = '" . (int) $additional_image['sort_order'] . "'");   


                if(!$image_exists) { 
                    try {

                        $content = file_get_contents($image_content_directory);

                        if (!is_dir(DIR_IMAGE . '/' . dirname($image_to_update))) {
                            mkdir(DIR_IMAGE . '/' . dirname($image_to_update), 0777, true);
                        }


                        $result['img'] = $this->saveImageToDir($content, $new_filename, $is_webp);            
                    } 
                    catch (Exception $ex) {
                        array_push($err, $data['ean'] . ':' . $ex->getMessage());
                    }
                }
            }
        }

        if (isset($data['product_description'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_description WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['product_description'] as $key => $value) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_description SET product_id = '" . (int) $product_id . "', 
                language_id = '" . (int) $language_id . "', name = '" . html_entity_decode($this->db->escape($value['name'])) . "', 
                description = '" . html_entity_decode($this->db->escape($value['description'])) . "', tag = '" . $this->db->escape($value['tag']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
            }
        }

        if (isset($data['product_store'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_to_store WHERE product_id = '" . (int) $product_id . "'");
            foreach ($data['product_store'] as $store_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_store SET product_id = '" . (int) $product_id . "', store_id = '" . (int) $store_id . "'");
            }
        }

        if(isset($fields) && in_array('product_attribute', $fields)){
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_attribute WHERE product_id = '" . (int) $product_id . "'");
            foreach ($data['product_attribute'] as $product_attribute_group) {
                $attr_query = $this->db->query("SELECT attribute_group_id FROM " . DB_PREFIX . "attribute_group_description WHERE name = '" . $product_attribute_group['name'] ."' ");
                if( ($attr_query->num_rows) < 1){
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "attribute_group SET sort_order = '" . (int) $sort_order . "'");
		            $attribute_group_id = $this->db->getLastId();
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "attribute_group_description SET attribute_group_id = '" . (int) $attribute_group_id . "', name = '" . $product_attribute_group['name'] . "', language_id = '" . (int) $language_id . "'");
                }
                else{
                    foreach ($attr_query->rows as $attr) {
                        $attribute_group_id = $attr['attribute_group_id'];
                    }
                }
                    
                foreach($product_attribute_group['attribute'] as $product_attribute){
                    $attr_query2 = $this->db->query("SELECT " . DB_PREFIX . "attribute_description.attribute_id, attribute_group_id FROM " . DB_PREFIX . "attribute_description LEFT JOIN " . DB_PREFIX . "attribute on " . DB_PREFIX . "attribute_description.attribute_id = " . DB_PREFIX . "attribute.attribute_id WHERE name = '" . $product_attribute['name'] ."' AND attribute_group_id = '".$attribute_group_id."' ");
                    if( ($attr_query2->num_rows) < 1){
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "attribute SET sort_order = '" . (int) $sort_order . "', attribute_group_id = '" . (int) $attribute_group_id . "'");
    		            $attribute_id = $this->db->getLastId();
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "attribute_description SET attribute_id = '" . (int) $attribute_id . "', name = '" . $product_attribute['name'] . "', language_id = '" . (int) $language_id . "'");
                    }
                    else{
                        foreach ($attr_query2->rows as $attr){
                            $attribute_id = $attr['attribute_id'];
                        }
                    }
                    
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_attribute SET product_id = '".(int) $product_id."', attribute_id = '".(int) $attribute_id."', text = '".$product_attribute['text']."', language_id = '".(int) $language_id ."' ");
                }
            }
        }

        if (isset($data['product_option'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_option WHERE product_id = '" . (int) $product_id . "'");
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_option_value WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['product_option'] as $product_option) {
                if ('select' == $product_option['type'] || 'radio' == $product_option['type'] || 'checkbox' == $product_option['type'] || 'image' == $product_option['type']) {
                    if (isset($product_option['product_option_value'])) {
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option SET product_option_id = '" . (int) $product_option['product_option_id'] . "', product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', required = '" . (int) $product_option['required'] . "'");

                        $product_option_id = $this->db->getLastId();

                        foreach ($product_option['product_option_value'] as $product_option_value) {
                            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option_value SET product_option_value_id = '" . (int) $product_option_value['product_option_value_id'] . "', product_option_id = '" . (int) $product_option_id . "', product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', option_value_id = '" . (int) $product_option_value['option_value_id'] . "', quantity = '" . (int) $product_option_value['quantity'] . "', subtract = '" . (int) $product_option_value['subtract'] . "', price = '" . (float) $product_option_value['price'] . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', points = '" . (int) $product_option_value['points'] . "', points_prefix = '" . $this->db->escape($product_option_value['points_prefix']) . "', weight = '" . (float) $product_option_value['weight'] . "', weight_prefix = '" . $this->db->escape($product_option_value['weight_prefix']) . "'");
                        }
                    }
                } else {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option SET product_option_id = '" . (int) $product_option['product_option_id'] . "', product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', value = '" . $this->db->escape($product_option['value']) . "', required = '" . (int) $product_option['required'] . "'");
                }
            }
        }

        if (isset($data['product_recurring'])) {
            $this->db->query('DELETE FROM `' . DB_PREFIX . 'product_recurring` WHERE product_id = ' . (int) $product_id);

            foreach ($data['product_recurring'] as $product_recurring) {
                $this->db->query('INSERT INTO `' . DB_PREFIX . 'product_recurring` SET `product_id` = ' . (int) $product_id . ', customer_group_id = ' . (int) $product_recurring['customer_group_id'] . ', `recurring_id` = ' . (int) $product_recurring['recurring_id']);
            }
        }

        if (isset($data['product_discount'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_discount WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['product_discount'] as $product_discount) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_discount SET product_id = '" . (int) $product_id . "', customer_group_id = '" . (int) $product_discount['customer_group_id'] . "', quantity = '" . (int) $product_discount['quantity'] . "', priority = '" . (int) $product_discount['priority'] . "', price = '" . (float) $product_discount['price'] . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
            }
        }

        if (isset($data['product_special'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_special WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['product_special'] as $product_special) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_special SET product_id = '" . (int) $product_id . "', customer_group_id = '" . (int) $product_special['customer_group_id'] . "', priority = '" . (int) $product_special['priority'] . "', price = '" . (float) $product_special['price'] . "', date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
            }
        }




        if (isset($data['product_download'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_to_download WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['product_download'] as $download_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_download SET product_id = '" . (int) $product_id . "', download_id = '" . (int) $download_id . "'");
            }
        }

        if (isset($data['product_category'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_to_category WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['product_category'] as $category_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_category SET product_id = '" . (int) $product_id . "', category_id = '" . (int) $category_id . "'");
            }
        }

        if (isset($data['product_filter'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_filter WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['product_filter'] as $filter_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_filter SET product_id = '" . (int) $product_id . "', filter_id = '" . (int) $filter_id . "'");
            }
        }

        if (isset($data['product_related'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $product_id . "'");
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE related_id = '" . (int) $product_id . "'");

            foreach ($data['product_related'] as $related_id) {
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $product_id . "' AND related_id = '" . (int) $related_id . "'");
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_related SET product_id = '" . (int) $product_id . "', related_id = '" . (int) $related_id . "'");
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $related_id . "' AND related_id = '" . (int) $product_id . "'");
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_related SET product_id = '" . (int) $related_id . "', related_id = '" . (int) $product_id . "'");
            }
        }

        if (isset($data['product_reward'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_reward WHERE product_id = '" . (int) $product_id . "'");
            foreach ($data['product_reward'] as $customer_group_id => $value) {
                if ((int) $value['points'] > 0) {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_reward SET product_id = '" . (int) $product_id . "', customer_group_id = '" . (int) $customer_group_id . "', points = '" . (int) $value['points'] . "'");
                }
            }
        }

        if (isset($data['product_seo_url'])) {
            // SEO URL
            $this->db->query('DELETE FROM ' . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int) $product_id . "'");

            foreach ($data['product_seo_url'] as $store_id => $language) {
                foreach ($language as $key => $keyword) {
                    if (!empty($keyword)) {
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "seo_url SET store_id = '" . (int) $store_id . "', language_id = '" . (int) $language_id . "', query = 'product_id=" . (int) $product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
                    }
                }
            }
        }

        if (isset($data['product_layout'])) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int) $product_id . "'");

            foreach ($data['product_layout'] as $store_id => $layout_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_layout SET product_id = '" . (int) $product_id . "', store_id = '" . (int) $store_id . "', layout_id = '" . (int) $layout_id . "'");
            }
        }

                //EXTRAS
        if (isset($data['short_description']) && strlen($data['short_description']) > 1){
            $this->insertShortDescription($data, $product_id);
        }

        $this->cache->delete('product');

        return $product_id;
    }

    public function addProduct($data, $priceIds)
    {
        $language_id = (int) $this->config->get('config_language_id');

        $data['manufacturer'] = isset($data['manufacturer']) ? $data['manufacturer'] : '';
        if ('' == $data['manufacturer']) {
            return 'rejected:manufacturer_';
        }

        $data['sku'] = isset($data['sku']) ? $data['sku'] : '';
        $data['manufacturer_id'] = isset($data['manufacturer_id']) ? $data['manufacturer_id'] : 0;
        $mnf = $this->db->query('SELECT * FROM ' . DB_PREFIX . "manufacturer WHERE name='" . $data['manufacturer'] . "' LIMIT 1");
        if ($mnf->num_rows) {
            $data['manufacturer_id'] = $mnf->row['manufacturer_id'];
        } else {
            // Manufacturer does not exist. add it
            $manid = $this->addManufacturer($data['manufacturer']);
            $data['manufacturer_id'] = $manid;
        }

        $data['weight'] = isset($data['weight']) ? $data['weight'] : 0;
        $data['weight_class_id'] = isset($data['weight_class_id']) ? $data['weight_class_id'] : 1;
        $data['points'] = 0;

        // add manufacturer if it does not exist

        $this->db->query('INSERT INTO ' . DB_PREFIX . "product SET model = '" . $this->db->escape($data['model']) . "', sku = '" . $this->db->escape($data['sku']) . "', upc = '" . $this->db->escape($data['upc']) . "', ean = '" . $this->db->escape($data['ean']) . "', jan = '" . $this->db->escape($data['jan']) . "', isbn = '" . $this->db->escape($data['isbn']) . "', mpn = '" . $this->db->escape($data['mpn']) . "', location = '" . $this->db->escape($data['location']) . "', quantity = '" . (int) $data['quantity'] . "', minimum = '" . (int) $data['minimum'] . "', subtract = '" . (int) $data['subtract'] . "', stock_status_id = '" . (int) $data['stock_status_id'] . "', date_available = '" . $this->db->escape($data['date_available']) . "', manufacturer_id = '" . (int) $data['manufacturer_id'] . "', shipping = '" . (int) $data['shipping'] . "', price = '" . (float) $data['price'] . "', points = '" . (int) $data['points'] . "', weight = '" . (float) $data['weight'] . "', weight_class_id = '" . (int) $data['weight_class_id'] . "', length = '" . (float) $data['length'] . "', width = '" . (float) $data['width'] . "', height = '" . (float) $data['height'] . "', length_class_id = '" . (int) $data['length_class_id'] . "', status = '" . (int) $data['status'] . "', tax_class_id = '" . (int) $data['tax_class_id'] . "', sort_order = '" . (int) $data['sort_order'] . "', date_added = NOW(), date_modified = NOW()");

        $product_id = $this->db->getLastId();


        /** PRICES */
        if ($data['use_price_plugin'] == 1) {
            foreach ($data['group_prices'] as $groupPrice) {

                // {_id: "2", _price: "66.48", opname: "Retail"}

                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_price 
				    SET product_id='" . $product_id . "', 
				    customer_group_id='" . $this->db->escape($groupPrice['_id']) . "', 
				    price='" . $this->db->escape($groupPrice['_price']) . "'
				    ON DUPLICATE KEY UPDATE price='" . $this->db->escape($groupPrice['_price']) . "'");
            }
            // //Add the prices
            // $this->db->query('INSERT INTO ' . DB_PREFIX . "product_price 
            // 		SET product_id='" . $product_id . "', 
            // 		customer_group_id='" . $this->db->escape($priceIds['Wholesale']) . "', 
            // 		price='" . $this->db->escape($data['wholesale_price']) . "'
            // 		ON DUPLICATE KEY UPDATE price='" . $this->db->escape($data['wholesale_price']) . "'");

            // $this->db->query('INSERT INTO ' . DB_PREFIX . "product_price 
            // 		SET product_id='" . $product_id . "', 
            // 		customer_group_id='" . $this->db->escape($priceIds['Retail']) . "', 
            // 		price='" . $this->db->escape($data['retail_price']) . "'

            // 		ON DUPLICATE KEY UPDATE price='" . $this->db->escape($data['wholesale_price']) . "'");

        }


        // ---------------------------

        if (isset($data['image'])) {
            $this->db->query('UPDATE ' . DB_PREFIX . "product SET image = '" . $this->db->escape($data['image']) . "' WHERE product_id = '" . (int) $product_id . "'");
        }

        foreach ($data['product_description'] as $key => $value) {
            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_description 
            SET product_id = '" . (int) $product_id . "', 
            language_id = '" . (int) $language_id . "', 
            name = '" . $this->db->escape($value['name']) . "', description = '" .
                $this->db->escape($value['description']) . "', tag = '" . $this->db->escape($value['tag']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
        }

        if (isset($data['product_store'])) {
            foreach ($data['product_store'] as $store_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_store SET product_id = '" . (int) $product_id . "', store_id = '" . (int) $store_id . "'");
            }
        }
        //add it to the default store
        else {
            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_store SET product_id = '" . (int) $product_id . "', store_id = '0'");
        }

        if (isset($data['product_attribute'])) {
            foreach ($data['product_attribute'] as $product_attribute) {
                if ($product_attribute['attribute_id']) {
                    // Removes duplicates
                    $this->db->query('DELETE FROM ' . DB_PREFIX . "product_attribute WHERE product_id = '" . (int) $product_id . "' AND attribute_id = '" . (int) $product_attribute['attribute_id'] . "'");

                    foreach ($product_attribute['product_attribute_description'] as $key => $product_attribute_description) {
                        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_attribute WHERE product_id = '" . (int) $product_id . "' AND attribute_id = '" . (int) $product_attribute['attribute_id'] . "' AND language_id = '" . (int) $language_id . "'");

                        $this->db->query('INSERT INTO ' . DB_PREFIX . "product_attribute SET product_id = '" . (int) $product_id . "', attribute_id = '" . (int) $product_attribute['attribute_id'] . "', language_id = '" . (int) $language_id . "', text = '" . $this->db->escape($product_attribute_description['text']) . "'");
                    }
                }
            }
        }

        if (isset($data['product_option'])) {
            foreach ($data['product_option'] as $product_option) {
                if ('select' == $product_option['type'] || 'radio' == $product_option['type'] || 'checkbox' == $product_option['type'] || 'image' == $product_option['type']) {
                    if (isset($product_option['product_option_value'])) {
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option SET product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', required = '" . (int) $product_option['required'] . "'");

                        $product_option_id = $this->db->getLastId();

                        foreach ($product_option['product_option_value'] as $product_option_value) {
                            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option_value SET product_option_id = '" . (int) $product_option_id . "', product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', option_value_id = '" . (int) $product_option_value['option_value_id'] . "', quantity = '" . (int) $product_option_value['quantity'] . "', subtract = '" . (int) $product_option_value['subtract'] . "', price = '" . (float) $product_option_value['price'] . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', points = '" . (int) $product_option_value['points'] . "', points_prefix = '" . $this->db->escape($product_option_value['points_prefix']) . "', weight = '" . (float) $product_option_value['weight'] . "', weight_prefix = '" . $this->db->escape($product_option_value['weight_prefix']) . "'");
                        }
                    }
                } else {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option SET product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', value = '" . $this->db->escape($product_option['value']) . "', required = '" . (int) $product_option['required'] . "'");
                }
            }
        }

        if (isset($data['product_recurring'])) {
            foreach ($data['product_recurring'] as $recurring) {
                $this->db->query('INSERT INTO `' . DB_PREFIX . 'product_recurring` SET `product_id` = ' . (int) $product_id . ', customer_group_id = ' . (int) $recurring['customer_group_id'] . ', `recurring_id` = ' . (int) $recurring['recurring_id']);
            }
        }

        if (isset($data['product_discount'])) {
            foreach ($data['product_discount'] as $product_discount) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_discount SET product_id = '" . (int) $product_id . "', customer_group_id = '" . (int) $product_discount['customer_group_id'] . "', quantity = '" . (int) $product_discount['quantity'] . "', priority = '" . (int) $product_discount['priority'] . "', price = '" . (float) $product_discount['price'] . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
            }
        }

        if (isset($data['product_special'])) {
            foreach ($data['product_special'] as $product_special) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_special SET product_id = '" . (int) $product_id . "', customer_group_id = '" . (int) $product_special['customer_group_id'] . "', priority = '" . (int) $product_special['priority'] . "', price = '" . (float) $product_special['price'] . "', date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
            }
        }

        if (isset($data['product_image'])) {
            foreach ($data['product_image'] as $product_image) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_image SET product_id = '" . (int) $product_id . "', image = '" . $this->db->escape($product_image['image']) . "', sort_order = '" . (int) $product_image['sort_order'] . "'");
            }
        }

        if (isset($data['product_download'])) {
            foreach ($data['product_download'] as $download_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_download SET product_id = '" . (int) $product_id . "', download_id = '" . (int) $download_id . "'");
            }
        }

        if (isset($data['product_category'])) {
            foreach ($data['product_category'] as $category_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_category SET product_id = '" . (int) $product_id . "', category_id = '" . (int) $category_id . "'");
            }
        }

        if (isset($data['product_filter'])) {
            foreach ($data['product_filter'] as $filter_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_filter SET product_id = '" . (int) $product_id . "', filter_id = '" . (int) $filter_id . "'");
            }
        }

        if (isset($data['product_related'])) {
            foreach ($data['product_related'] as $related_id) {
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $product_id . "' AND related_id = '" . (int) $related_id . "'");
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_related SET product_id = '" . (int) $product_id . "', related_id = '" . (int) $related_id . "'");
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $related_id . "' AND related_id = '" . (int) $product_id . "'");
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_related SET product_id = '" . (int) $related_id . "', related_id = '" . (int) $product_id . "'");
            }
        }

        if (isset($data['product_reward'])) {
            foreach ($data['product_reward'] as $customer_group_id => $product_reward) {
                if ((int) $product_reward['points'] > 0) {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_reward SET product_id = '" . (int) $product_id . "', customer_group_id = '" . (int) $customer_group_id . "', points = '" . (int) $product_reward['points'] . "'");
                }
            }
        }

        // SEO URL
        if (isset($data['product_seo_url'])) {
            foreach ($data['product_seo_url'] as $store_id => $language) {
                foreach ($language as $key => $keyword) {
                    if (!empty($keyword)) {
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "seo_url SET store_id = '" . (int) $store_id . "', language_id = '" . (int) $language_id . "', query = 'product_id=" . (int) $product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
                    }
                }
            }
        }

        if (isset($data['product_layout'])) {
            foreach ($data['product_layout'] as $store_id => $layout_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_layout SET product_id = '" . (int) $product_id . "', store_id = '" . (int) $store_id . "', layout_id = '" . (int) $layout_id . "'");
            }
        }

        $this->cache->delete('product');

        return $product_id;
    }

    public function updateExisting($product, $priceIds)
    {
        $p = $this->db->query('SELECT product_id FROM ' . DB_PREFIX . 'product 					
				WHERE 
				' . $product['barcodeonopencart_field'] . " = '" . $product['barcode'] . "'");

        $query = $this->db->query('UPDATE ' . DB_PREFIX . "product 
				SET price='" . $product['price'] . "',
				quantity='" . $product['quantity'] . "'
				WHERE product_id='" . $p->row['product_id'] . "'
				");

        /** PRICES */
        if ($product['use_price_plugin'] == 1) {
            foreach ($product['group_prices'] as $groupPrice) {

                $this->db->query('UPDATE ' . DB_PREFIX . "product_price 
                    SET price='" . $this->db->escape($groupPrice['_price']) . "'
                    WHERE product_id='" . $p->row['product_id'] . "' and customer_group_id='" . $this->db->escape($groupPrice['_id']) . "'");
            }
        }

        // ---------------------------
        return $p->row['product_id'];
    }

    public function addFullProduct($data, $fields)
    {

        $language_id = (int) $this->config->get('config_language_id');
        $err = [];
        $result = [];
        
        
        
            $productTableFields = [
            'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'location',
            'quantity', 'minimum', 'subtract', 'stock_status_id', 'date_available', 'manufacturer_id', 'shipping',
            'price', 'points', 'weight', 'weight_class_id', 'length', 'width', 'height', 'length_class_id', 'status',
            'tax_class_id', 'sort_order'
        ];

        $sql = 'INSERT INTO ' . DB_PREFIX . 'product SET ';


        foreach ($data as $k => $v) {
            if (in_array($k, $productTableFields)) {
                $sql .= ' ' . $k . " = '" . $this->db->escape($v) . "',
            ";
            }
        }

        $sql .= "date_added = NOW(), date_modified = NOW()";

        $this->db->query($sql);
        

        $product_id = $this->db->getLastId();

        $result['id'] = $product_id;

        if (isset($data['image'])) {
            try {

                $query = $this->db->query('SELECT image FROM ' . DB_PREFIX . "product WHERE product_id = '". (int) $product_id ."'");
                foreach ($query->rows as $qry){
                    $img = $qry['image'];
                }
                
                $imgToCompare = $data['image'];
                $imgToCompare = trim(substr($imgToCompare, strrpos($imgToCompare, '/') + 1));
                $imgToCompare =  str_replace(trim(substr($imgToCompare, strrpos($imgToCompare, '.'))), '', $imgToCompare);

                if(strpos(trim($img), trim($imgToCompare)) == false){

                    $postfix = '';
                    $extension = '';
                    $is_webp = false;

                    $image_to_add = $data['image'];
                    $image_to_add = $this->addCatalogToImage($image_to_add);

                    $content_image_url = str_replace(' ', '%20', $data['image']);
                    $content_image_dir = $data['imageUrl'];

                    $image_filetype = substr($data['image'], -5);

                    if(! (strpos($image_filetype, '.') !== false) ){
                        $postfix = '.jpg';
                        $extension = '?output=jpeg';
                    }

                    if($image_filetype == '.webp'){
                        $postfix = '.jpg';
                        $extension = '?output=jpeg';
                        $image_to_add = substr($image_to_add, 0, -5);
                        $is_webp = true;
                    }

                    $result['content_img'] = $content_image_dir . '/' . $content_image_url . $extension;

                    $content = file_get_contents($content_image_dir . '/' . $content_image_url. $extension);
                    

                    $sanitizedFilename = preg_replace('/[^a-zA-Z0-9\-\._]/', '', basename($image_to_add));
                    $newFilename = dirname($image_to_add) . '/' . $sanitizedFilename . $postfix;

                    if (!is_dir(DIR_IMAGE . '/' . dirname($image_to_add))) {
                        mkdir(DIR_IMAGE . '/' . dirname($image_to_add), 0777, true);
                    }

                    $result['img'] = $this->saveImageToDir($content, $newFilename, $is_webp);

                    $this->db->query('UPDATE ' . DB_PREFIX . "product SET image = '" . $this->db->escape($newFilename). "' WHERE product_id = '" . (int) $product_id . "'");
                    $result['img_url_to_add'] = $newFilename;

                }
            } catch (Exception $ex) {
                array_push($err, $data['ean'] . ':' . $ex->getMessage());
            }
        }

        if(isset($data['additional_images'])){
            $query = $this->db->query('SELECT image FROM ' . DB_PREFIX . "product_image WHERE product_id = '". (int) $product_id ."'");
            foreach ($data['additional_images'] as $additional_image){
                $existsFlag = false;
                foreach ($query->rows as $qry){
                    $img = $qry['image'];
                    $imgToCompare = $additional_image['image'];
                    $imgToCompare = trim(substr($imgToCompare, strrpos($imgToCompare, '/') + 1));
                    $imgToCompare =  str_replace(trim(substr($imgToCompare, strrpos($imgToCompare, '.'))), '', $imgToCompare);
                    if(strpos(trim($img), trim($imgToCompare)) == true){
                        $existsFlag = true;
                    }
                }
                if(!$existsFlag)
                { 
                    try {

                        $postfix = '';
                        $extension = '';
                        $iswebp = false;
    
                        $image_to_add = $additional_image['image'];
                        $image_to_add = $this->addCatalogToImage($image_to_add);
    
                        $content_image_url = str_replace(' ', '%20', $additional_image['image']);
    
                        $image_filetype = substr($additional_image['image'], -5);
    
                        if(! (strpos($image_filetype, '.') !== false) ){
                            $postfix = '.jpg';
                            $extension = '?output=jpeg';
                        }
    
                        if($image_filetype == '.webp'){
                            $postfix = '.jpg';
                            $extension = '?output=jpeg';
                            $image_to_add = substr($image_to_add, 0, -5);
                            $iswebp = true;
                        }


                        if(isset($additional_image['base_image_url'])){
                            $image_directory = $additional_image['base_image_url'];
                        }
                        else{
                            $image_directory = $data['imageUrl'];
                        }

        
                        $content = file_get_contents($image_directory . '/' . str_replace(' ', '%20', $additional_image['image']) . $extension);

                        if (!is_dir(DIR_IMAGE . '/' . dirname($image_to_add))) {
                            mkdir(DIR_IMAGE . '/' . dirname($image_to_add), 0777, true);
                        }



                        $sanitizedFilename = preg_replace('/[^a-zA-Z0-9\-\._]/', '', basename($image_to_add));
                        $newFilename = dirname($image_to_add) . '/' . $sanitizedFilename . $postfix;
        
                        $result['img'] = $this->saveImageToDir($content, $newFilename, $iswebp);


                        $this->db->query('INSERT INTO ' . DB_PREFIX . "product_image SET product_id = '" . (int) $product_id . "', 
                        image = '" . $this->db->escape($newFilename) . "', sort_order = '" . (int) $additional_image['sort_order'] . "'");                
                    } catch (Exception $ex) {
                        array_push($err, $data['ean'] . ':' . $ex->getMessage());
                    }
                }
            }
        }

        foreach ($data['product_description'] as $key => $value) {
            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_description SET 
            product_id = '" . (int) $product_id . "', 
            language_id = '" . (int) $language_id . "', 
            name = '" . html_entity_decode($this->db->escape($value['name'])) . "', 
            description = '" . html_entity_decode($this->db->escape($value['description'])) . "', tag = '" . $this->db->escape($value['tag']) . "', 
            meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
        }

        if (isset($data['product_store'])) {
            foreach ($data['product_store'] as $store_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_store SET product_id = '" . (int) $product_id . "', store_id = '" . (int) $store_id . "'");
            }
        }

        if(isset($fields) && in_array('product_attribute', $fields)) {
            $this->db->query('DELETE FROM ' . DB_PREFIX . "product_attribute WHERE product_id = '" . (int) $product_id . "'");
            foreach ($data['product_attribute'] as $product_attribute_group) {
                $attr_query = $this->db->query("SELECT attribute_group_id FROM " . DB_PREFIX . "attribute_group_description WHERE name = '" . $product_attribute_group['name'] ."' ");
                if( ($attr_query->num_rows) < 1)
                {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "attribute_group SET sort_order = '" . (int) $sort_order . "'");
		            $attribute_group_id = $this->db->getLastId();
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "attribute_group_description SET attribute_group_id = '" . (int) $attribute_group_id . "', name = '" . $product_attribute_group['name'] . "', language_id = '" . (int) $language_id . "'");
                }
                else{
                    foreach ($attr_query->rows as $attr) {
                        $attribute_group_id = $attr['attribute_group_id'];
                    }
                }
                    
                foreach($product_attribute_group['attribute'] as $product_attribute)
                {
                    $attr_query2 = $this->db->query("SELECT " . DB_PREFIX . "attribute_description.attribute_id, attribute_group_id FROM " . DB_PREFIX . "attribute_description LEFT JOIN " . DB_PREFIX . "attribute on " . DB_PREFIX . "attribute_description.attribute_id = " . DB_PREFIX . "attribute.attribute_id WHERE name = '" . $product_attribute['name'] ."' ");
                    if( ($attr_query2->num_rows) < 1)
                    {
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "attribute SET sort_order = '" . (int) $sort_order . "', attribute_group_id = '" . (int) $attribute_group_id . "'");
    		            $attribute_id = $this->db->getLastId();
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "attribute_description SET attribute_id = '" . (int) $attribute_id . "', name = '" . $product_attribute['name'] . "', language_id = '" . (int) $language_id . "'");
                    }
                    else{
                        foreach ($attr_query2->rows as $attr){
                            $attribute_id = $attr['attribute_id'];
                        }
                    }
                    
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_attribute SET product_id = '".(int) $product_id."', attribute_id = '".(int) $attribute_id."', text = '".$product_attribute['text']."', language_id = '".(int) $language_id ."' ");
                }
            }
        }

        if (isset($data['product_option'])) {
            foreach ($data['product_option'] as $product_option) {
                if ('select' == $product_option['type'] || 'radio' == $product_option['type'] || 'checkbox' == $product_option['type'] || 'image' == $product_option['type']) {
                    if (isset($product_option['product_option_value'])) {
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option SET product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', required = '" . (int) $product_option['required'] . "'");

                        $product_option_id = $this->db->getLastId();

                        foreach ($product_option['product_option_value'] as $product_option_value) {
                            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option_value SET product_option_id = '" . (int) $product_option_id . "', product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', option_value_id = '" . (int) $product_option_value['option_value_id'] . "', quantity = '" . (int) $product_option_value['quantity'] . "', subtract = '" . (int) $product_option_value['subtract'] . "', price = '" . (float) $product_option_value['price'] . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', 
                            points = '" . (int) (isset($product_option_value['points']) ? $product_option_value['points'] : 0) . "', 
                            points_prefix = '" . $this->db->escape((isset($product_option_value['points_prefix']) ? $product_option_value['points_prefix'] : '')) . "', 
                            weight = '" . (float) (isset($product_option_value['weight']) ? $product_option_value['weight'] : 0) . "', 
                            weight_prefix = '" . $this->db->escape(isset($product_option_value['weight_prefix']) ? $product_option_value['weight_prefix'] : '') . "'");
                        }
                    }
                } else {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_option SET product_id = '" . (int) $product_id . "', option_id = '" . (int) $product_option['option_id'] . "', value = '" . $this->db->escape($product_option['value']) . "', required = '" . (int) $product_option['required'] . "'");
                }
            }
        }

        if (isset($data['product_recurring'])) {
            foreach ($data['product_recurring'] as $recurring) {
                $this->db->query('INSERT INTO `' . DB_PREFIX . 'product_recurring` SET `product_id` = ' . (int) $product_id . ', customer_group_id = ' . (int) $recurring['customer_group_id'] . ', `recurring_id` = ' . (int) $recurring['recurring_id']);
            }
        }

        if (isset($data['product_discount'])) {
            foreach ($data['product_discount'] as $product_discount) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_discount SET product_id = '" . (int) $product_id . "', customer_group_id = '" . (int) $product_discount['customer_group_id'] . "', quantity = '" . (int) $product_discount['quantity'] . "', priority = '" . (int) $product_discount['priority'] . "', price = '" . (float) $product_discount['price'] . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', 
                date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
            }
        }

        if (isset($data['product_special'])) {
            foreach ($data['product_special'] as $product_special) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_special SET product_id = '" . (int) $product_id . "', 
                customer_group_id = '" . (int) $product_special['customer_group_id'] . "', 
                priority = '" . (int) $product_special['priority'] . "', 
                price = '" . (float) $product_special['price'] . "', 
                date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
            }
        }

        if (isset($data['product_download'])) {
            foreach ($data['product_download'] as $download_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_download SET product_id = '" . (int) $product_id . "', download_id = '" . (int) $download_id . "'");
            }
        }

        if (isset($data['product_category'])) {
            foreach ($data['product_category'] as $category_id) {
                try {
                    $this->db->query(
                        'INSERT INTO ' . DB_PREFIX . "product_to_category (product_id,category_id) 
                        VALUES('" . (int) $product_id . "','" . (int) $category_id . "')
                    
                        ON DUPLICATE KEY UPDATE
                        product_id = '" . (int) $product_id . "', 
                        category_id = '" . (int) $category_id . "'"
                    );
                } catch (Exception $ex) {
                    array_push($err, $data['ean'] . ':' . $ex->getMessage());
                }
            }
        }

        if (isset($data['product_filter'])) {
            foreach ($data['product_filter'] as $filter_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_filter SET product_id = '" . (int) $product_id . "', filter_id = '" . (int) $filter_id . "'");
            }
        }

        if (isset($data['product_related'])) {
            foreach ($data['product_related'] as $related_id) {
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $product_id . "' AND related_id = '" . (int) $related_id . "'");
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_related SET product_id = '" . (int) $product_id . "', related_id = '" . (int) $related_id . "'");
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_related WHERE product_id = '" . (int) $related_id . "' AND related_id = '" . (int) $product_id . "'");
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_related SET product_id = '" . (int) $related_id . "', related_id = '" . (int) $product_id . "'");
            }
        }

        if (isset($data['product_reward'])) {
            foreach ($data['product_reward'] as $customer_group_id => $product_reward) {
                if ((int) $product_reward['points'] > 0) {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_reward SET product_id = '" . (int) $product_id . "', customer_group_id = '" . (int) $customer_group_id . "', points = '" . (int) $product_reward['points'] . "'");
                }
            }
        }

        // SEO URL
        if (isset($data['product_seo_url'])) {
            foreach ($data['product_seo_url'] as $store_id => $language) {
                foreach ($language as $key => $keyword) {
                    if (!empty($keyword)) {
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "seo_url SET store_id = '" . (int) $store_id . "', language_id = '" . (int) $language_id . "', query = 'product_id=" . (int) $product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
                    }
                }
            }
        }

        if (isset($data['product_layout'])) {
            foreach ($data['product_layout'] as $store_id => $layout_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_to_layout SET product_id = '" . (int) $product_id . "', store_id = '" . (int) $store_id . "', layout_id = '" . (int) $layout_id . "'");
            }
        }

        //EXTRAS
        if (isset($data['short_description']) && strlen($data['short_description']) > 1)
        {
            $this->insertShortDescription($data, $product_id);
        }

        $this->cache->delete('product');

        $result['error'] = $err;
        return $result;
    }

    //Despite the name this func can bring back skus as well
    public function getEans($s_id)
    {
        $query = $this->db->query('SELECT DISTINCT ' . $s_id . 
         ' FROM ' . DB_PREFIX . 'product ');

        return $query->rows;
    }

    public function getProduct($product_id)
    {
        $query = $this->db->query('SELECT DISTINCT *, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM ' . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int) $this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int) $this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int) $this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order FROM " . DB_PREFIX . 'product p LEFT JOIN ' . DB_PREFIX . 'product_description pd ON (p.product_id = pd.product_id) LEFT JOIN ' . DB_PREFIX . 'product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN ' . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id) WHERE p.product_id = '" . (int) $product_id . "' AND pd.language_id = '" . (int) $this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int) $this->config->get('config_store_id') . "'");

        if ($query->num_rows) {
            return [
                'product_id' => $query->row['product_id'],
                'name' => $query->row['name'],
                'description' => $query->row['description'],
                'meta_title' => $query->row['meta_title'],
                'meta_description' => $query->row['meta_description'],
                'meta_keyword' => $query->row['meta_keyword'],
                'tag' => $query->row['tag'],
                'model' => $query->row['model'],
                'sku' => $query->row['sku'],
                'upc' => $query->row['upc'],
                'ean' => $query->row['ean'],
                'jan' => $query->row['jan'],
                'isbn' => $query->row['isbn'],
                'mpn' => $query->row['mpn'],
                'location' => $query->row['location'],
                'quantity' => $query->row['quantity'],
                'stock_status' => $query->row['stock_status'],
                'image' => $query->row['image'],
                'manufacturer_id' => $query->row['manufacturer_id'],
                'manufacturer' => $query->row['manufacturer'],
                'price' => ($query->row['discount'] ? $query->row['discount'] : $query->row['price']),
                'special' => $query->row['special'],
                'reward' => $query->row['reward'],
                'points' => $query->row['points'],
                'tax_class_id' => $query->row['tax_class_id'],
                'date_available' => $query->row['date_available'],
                'weight' => $query->row['weight'],
                'weight_class_id' => $query->row['weight_class_id'],
                'length' => $query->row['length'],
                'width' => $query->row['width'],
                'height' => $query->row['height'],
                'length_class_id' => $query->row['length_class_id'],
                'subtract' => $query->row['subtract'],
                'rating' => round($query->row['rating']),
                'reviews' => $query->row['reviews'] ? $query->row['reviews'] : 0,
                'minimum' => $query->row['minimum'],
                'sort_order' => $query->row['sort_order'],
                'status' => $query->row['status'],
                'date_added' => $query->row['date_added'],
                'date_modified' => $query->row['date_modified'],
                'viewed' => $query->row['viewed'],
            ];
        }

        return false;
    }

    public function getProducts($data = [])
    {
        $sql = 'SELECT p.product_id, (SELECT AVG(rating) AS total FROM ' . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= ' FROM ' . DB_PREFIX . 'category_path cp LEFT JOIN ' . DB_PREFIX . 'product_to_category p2c ON (cp.category_id = p2c.category_id)';
            } else {
                $sql .= ' FROM ' . DB_PREFIX . 'product_to_category p2c';
            }

            if (!empty($data['filter_filter'])) {
                $sql .= ' LEFT JOIN ' . DB_PREFIX . 'product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN ' . DB_PREFIX . 'product p ON (pf.product_id = p.product_id)';
            } else {
                $sql .= ' LEFT JOIN ' . DB_PREFIX . 'product p ON (p2c.product_id = p.product_id)';
            }
        } else {
            $sql .= ' FROM ' . DB_PREFIX . 'product p';
        }

        $sql .= ' LEFT JOIN ' . DB_PREFIX . 'product_description pd ON (p.product_id = pd.product_id) LEFT JOIN ' . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int) $this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int) $this->config->get('config_store_id') . "'";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int) $data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int) $data['filter_category_id'] . "'";
            }

            if (!empty($data['filter_filter'])) {
                $implode = [];

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int) $filter_id;
                }

                $sql .= ' AND pf.filter_id IN (' . implode(',', $implode) . ')';
            }
        }

        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
            $sql .= ' AND (';

            if (!empty($data['filter_name'])) {
                $implode = [];

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                foreach ($words as $word) {
                    $implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= ' ' . implode(' AND ', $implode) . '';
                }

                if (!empty($data['filter_description'])) {
                    $sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                }
            }

            if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                $sql .= ' OR ';
            }

            if (!empty($data['filter_tag'])) {
                $implode = [];

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

                foreach ($words as $word) {
                    $implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= ' ' . implode(' AND ', $implode) . '';
                }
            }

            if (!empty($data['filter_name'])) {
                $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
            }

            $sql .= ')';
        }

        if (!empty($data['filter_manufacturer_id'])) {
            $sql .= " AND p.manufacturer_id = '" . (int) $data['filter_manufacturer_id'] . "'";
        }

        $sql .= ' GROUP BY p.product_id';

        $sort_data = [
            'pd.name',
            'p.model',
            'p.quantity',
            'p.price',
            'rating',
            'p.sort_order',
            'p.date_added',
        ];

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            if ('pd.name' == $data['sort'] || 'p.model' == $data['sort']) {
                $sql .= ' ORDER BY LCASE(' . $data['sort'] . ')';
            } elseif ('p.price' == $data['sort']) {
                $sql .= ' ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)';
            } else {
                $sql .= ' ORDER BY ' . $data['sort'];
            }
        } else {
            $sql .= ' ORDER BY p.sort_order';
        }

        if (isset($data['order']) && ('DESC' == $data['order'])) {
            $sql .= ' DESC, LCASE(pd.name) DESC';
        } else {
            $sql .= ' ASC, LCASE(pd.name) ASC';
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= ' LIMIT ' . (int) $data['start'] . ',' . (int) $data['limit'];
        }

        $product_data = [];

        $query = $this->db->query($sql);

        foreach ($query->rows as $result) {
            $product_data[$result['product_id']] = $this->getProduct($result['product_id']);
        }

        return $product_data;
    }

    public function getProductSpecials($product_id)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_special WHERE product_id = '" . (int) $product_id . "' ORDER BY priority, price");

        return $query->rows;
    }

    public function getProductAttributes($product_id){
        $product_attribute_group_data = [];

        $product_attribute_group_query = $this->db->query('SELECT ag.attribute_group_id, agd.name FROM ' . DB_PREFIX . 'product_attribute pa LEFT JOIN ' . DB_PREFIX . 'attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN ' . DB_PREFIX . 'attribute_group ag ON (a.attribute_group_id = ag.attribute_group_id) LEFT JOIN ' . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id) WHERE pa.product_id = '" . (int) $product_id . "' AND agd.language_id = '" . (int) $this->config->get('config_language_id') . "' GROUP BY ag.attribute_group_id ORDER BY ag.sort_order, agd.name");

        foreach ($product_attribute_group_query->rows as $product_attribute_group) {
            $product_attribute_data = [];

            $product_attribute_query = $this->db->query('SELECT a.attribute_id, ad.name, pa.text FROM ' . DB_PREFIX . 'product_attribute pa LEFT JOIN ' . DB_PREFIX . 'attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN ' . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE pa.product_id = '" . (int) $product_id . "' AND a.attribute_group_id = '" . (int) $product_attribute_group['attribute_group_id'] . "' AND ad.language_id = '" . (int) $this->config->get('config_language_id') . "' AND pa.language_id = '" . (int) $this->config->get('config_language_id') . "' ORDER BY a.sort_order, ad.name");

            foreach ($product_attribute_query->rows as $product_attribute) {
                $product_attribute_data[] = [
                    'attribute_id' => $product_attribute['attribute_id'],
                    'name' => iconv("UTF-8","UTF-8//IGNORE",$product_attribute['name']),
                    'text' => iconv("UTF-8","UTF-8//IGNORE",$product_attribute['text']),
                ];
            }

            $product_attribute_group_data[] = [
                'attribute_group_id' => $product_attribute_group['attribute_group_id'],
                'name' => iconv("UTF-8","UTF-8//IGNORE",$product_attribute_group['name']),
                'attribute' => $product_attribute_data,
            ];
        }

        return $product_attribute_group_data;
    }

    public function getProductOptions($product_id)
    {
        $product_option_data = [];

        $product_option_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product_option po LEFT JOIN `' . DB_PREFIX . 'option` o ON (po.option_id = o.option_id) LEFT JOIN ' . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_id = '" . (int) $product_id . "' AND od.language_id = '" . (int) $this->config->get('config_language_id') . "' ORDER BY o.sort_order");

        foreach ($product_option_query->rows as $product_option) {
            $product_option_value_data = [];

            $product_option_value_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product_option_value pov LEFT JOIN ' . DB_PREFIX . 'option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN ' . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_id = '" . (int) $product_id . "' AND pov.product_option_id = '" . (int) $product_option['product_option_id'] . "' AND ovd.language_id = '" . (int) $this->config->get('config_language_id') . "' ORDER BY ov.sort_order");

            foreach ($product_option_value_query->rows as $product_option_value) {
                $product_option_value_data[] = [
                    'product_option_value_id' => $product_option_value['product_option_value_id'],
                    'option_value_id' => $product_option_value['option_value_id'],
                    'name' => $product_option_value['name'],
                    'image' => $product_option_value['image'],
                    'quantity' => $product_option_value['quantity'],
                    'subtract' => $product_option_value['subtract'],
                    'price' => $product_option_value['price'],
                    'price_prefix' => $product_option_value['price_prefix'],
                    'weight' => $product_option_value['weight'],
                    'weight_prefix' => $product_option_value['weight_prefix'],
                ];
            }

            $product_option_data[] = [
                'product_option_id' => $product_option['product_option_id'],
                'product_option_value' => $product_option_value_data,
                'option_id' => $product_option['option_id'],
                'name' => $product_option['name'],
                'type' => $product_option['type'],
                'value' => $product_option['value'],
                'required' => $product_option['required'],
            ];
        }

        return $product_option_data;
    }

    public function getProductDiscounts($product_id)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_discount WHERE product_id = '" . (int) $product_id . "' AND customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' AND quantity > 1 AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity ASC, priority ASC, price ASC");

        return $query->rows;
    }

    public function getProductImages($product_id, $imageOnly = false)
    {
        // str_replace(' ', '%20', str_replace('/', '&#47;', $data['image']));
        $otherfields = $imageOnly ? '' : 'product_image_id, product_id, ';

        $query = $this->db->query('
            SELECT 
            ' . $otherfields . '
            image, 
            sort_order 
            FROM ' . DB_PREFIX . "product_image 
            WHERE product_id = '" . (int) $product_id . "' ORDER BY sort_order ASC");

        return $query->rows;
    }

    public function getProductRelated($product_id)
    {
        $product_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product_related pr LEFT JOIN ' . DB_PREFIX . 'product p ON (pr.related_id = p.product_id) LEFT JOIN ' . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pr.product_id = '" . (int) $product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int) $this->config->get('config_store_id') . "'");

        foreach ($query->rows as $result) {
            $product_data[$result['related_id']] = $this->getProduct($result['related_id']);
        }

        return $product_data;
    }

    public function getProductLayoutId($product_id)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int) $product_id . "' AND store_id = '" . (int) $this->config->get('config_store_id') . "'");

        if ($query->num_rows) {
            return (int) $query->row['layout_id'];
        }

        return 0;
    }

    public function getCategories($product_id)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_to_category WHERE product_id = '" . (int) $product_id . "'");

        return $query->rows;
    }

    public function getTotalProducts($data = [])
    {
        $sql = 'SELECT COUNT(DISTINCT p.product_id) AS total';

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= ' FROM ' . DB_PREFIX . 'category_path cp LEFT JOIN ' . DB_PREFIX . 'product_to_category p2c ON (cp.category_id = p2c.category_id)';
            } else {
                $sql .= ' FROM ' . DB_PREFIX . 'product_to_category p2c';
            }

            if (!empty($data['filter_filter'])) {
                $sql .= ' LEFT JOIN ' . DB_PREFIX . 'product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN ' . DB_PREFIX . 'product p ON (pf.product_id = p.product_id)';
            } else {
                $sql .= ' LEFT JOIN ' . DB_PREFIX . 'product p ON (p2c.product_id = p.product_id)';
            }
        } else {
            $sql .= ' FROM ' . DB_PREFIX . 'product p';
        }

        $sql .= ' LEFT JOIN ' . DB_PREFIX . 'product_description pd ON (p.product_id = pd.product_id) LEFT JOIN ' . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int) $this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int) $this->config->get('config_store_id') . "'";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int) $data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int) $data['filter_category_id'] . "'";
            }

            if (!empty($data['filter_filter'])) {
                $implode = [];

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int) $filter_id;
                }

                $sql .= ' AND pf.filter_id IN (' . implode(',', $implode) . ')';
            }
        }

        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
            $sql .= ' AND (';

            if (!empty($data['filter_name'])) {
                $implode = [];

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                foreach ($words as $word) {
                    $implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= ' ' . implode(' AND ', $implode) . '';
                }

                if (!empty($data['filter_description'])) {
                    $sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                }
            }

            if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                $sql .= ' OR ';
            }

            if (!empty($data['filter_tag'])) {
                $implode = [];

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

                foreach ($words as $word) {
                    $implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= ' ' . implode(' AND ', $implode) . '';
                }
            }

            if (!empty($data['filter_name'])) {
                $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
            }

            $sql .= ')';
        }

        if (!empty($data['filter_manufacturer_id'])) {
            $sql .= " AND p.manufacturer_id = '" . (int) $data['filter_manufacturer_id'] . "'";
        }

        $query = $this->db->query($sql);

        return $query->row['total'];
    }

    public function getProfile($product_id, $recurring_id)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'recurring r JOIN ' . DB_PREFIX . "product_recurring pr ON (pr.recurring_id = r.recurring_id AND pr.product_id = '" . (int) $product_id . "') WHERE pr.recurring_id = '" . (int) $recurring_id . "' AND status = '1' AND pr.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "'");

        return $query->row;
    }

    public function getProfiles($product_id)
    {
        $query = $this->db->query('SELECT rd.* FROM ' . DB_PREFIX . 'product_recurring pr JOIN ' . DB_PREFIX . 'recurring_description rd ON (rd.language_id = ' . (int) $this->config->get('config_language_id') . ' AND rd.recurring_id = pr.recurring_id) JOIN ' . DB_PREFIX . 'recurring r ON r.recurring_id = rd.recurring_id WHERE pr.product_id = ' . (int) $product_id . " AND status = '1' AND pr.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' ORDER BY sort_order ASC");

        return $query->rows;
    }

    public function getTotalProductSpecials()
    {
        $query = $this->db->query('SELECT COUNT(DISTINCT ps.product_id) AS total FROM ' . DB_PREFIX . 'product_special ps LEFT JOIN ' . DB_PREFIX . 'product p ON (ps.product_id = p.product_id) LEFT JOIN ' . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int) $this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))");

        if (isset($query->row['total'])) {
            return $query->row['total'];
        }

        return 0;
    }

    /**
     * Get Products modified since.
     *
     * @param mixed $dateFrom
     * @param mixed $limit
     * @param mixed $status
     */
    public function getProductsModifiedSince($dateFrom, $status = '', $limit = -1)
    {
        $limitClause = $limit > 0 ? ' LIMIT ' . $limit : '';
        $query = $this->db->query('SELECT DISTINCT product_id FROM ' . DB_PREFIX . 'product p WHERE p.date_modified >="' . $dateFrom . '"  ' . $limitClause);

        return $query->rows;
    }

    /**
     * Export all product info.
     *
     * @param mixed $product_id
     */
    public function getFullProduct($product_id)
    {
        $data = [];
        $query = $this->db->query('SELECT DISTINCT * FROM ' . DB_PREFIX . "product p WHERE p.product_id = '" . (int) $product_id . "' AND p.status = 1");

        if ($query->num_rows) {
            $data = $query->row;

            $data['image'] = str_replace(' ', '%20', str_replace('/', '&#47;', $data['image']));
            $data['viewed'] = '0';

            $data['product_attribute'] = $this->getProductAttributes($product_id);
            try{
                $data['product_description'] = $this->getProductDescriptions($product_id); 
            }
            catch(Exception $ex){
                $data['product_description']['0'] = 
                [
                    'name' =>'Name',
                    'description' => '',
                    'meta_title' => '',
                    'meta_description' => '',
                    'meta_keyword' => '',
                    'tag' => '',
                ];
            }
            //$data['product_description'] = $this->getProductDescriptions2($product_id);



            $data['product_discount'] = $this->getProductDiscounts($product_id);
            $data['product_filter'] = $this->getProductFilters($product_id);
            $data['product_image'] = $this->getProductImages($product_id, true);
            $data['product_option'] = $this->getProductOptions($product_id);
            $data['product_related'] = $this->getProductRelated($product_id);
            $data['product_reward'] = $this->getProductRewards($product_id);
            $data['product_special'] = $this->getProductSpecials($product_id);
            $data['product_category'] = $this->getProductCategories($product_id);
            $data['product_download'] = $this->getProductDownloads($product_id);
            $data['product_layout'] = $this->getProductLayouts($product_id);
            $data['product_store'] = $this->getProductStores($product_id);
            $data['product_recurrings'] = $this->getRecurrings($product_id);

            //EXTRAS
            $data['expected_date'] = $this->getExtraField($product_id);
            $data['short_description'] = $this->getShortDescription($product_id);
            //$data['product_config_group'] = $this->getProductConfigGroup($product_id);
            //$data['product_config_categories'] = $this->getProductConfigCategories($product_id);
            //$data['product_config_products'] = $this->getProductConfigProducts($product_id);

             //$this->addProduct($data);
        }

        return $data;
    }

    public function getProductDescriptions($product_id){
        $product_description_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_description WHERE product_id = '" . (int) $product_id . "'");
        $language_id = (int) $this->config->get('config_language_id');

        $product_description_data['1'] = [
            'name' => 'Unnamed Product',
            'description' => '',
            'meta_title' => '',
            'meta_description' => '',
            'meta_keyword' => '',
            'tag' => '',
        ];

        foreach($query->rows as $description_row){
            if($description_row['language_id'] == $language_id){
                $description = $description_row['description'] ?? '';
                $description = htmlentities(mb_convert_encoding($description, 'UTF-8', 'UTF-8'), ENT_QUOTES);

                $product_description_data['1'] = [
                    'name' => $description_row['name'] ?? 'Unnamed Product',
                    'description' => $description,
                    'meta_title' => $description_row['meta_title'] ?? '',
                    'meta_description' => $description_row['meta_description'] ?? '',
                    'meta_keyword' => $description_row['meta_keyword'] ?? '',
                    'tag' => $description_row['tag'] ?? '',
                ];

                // if(strlen($product_description_data['0']['description']) > 100){
                //     $product_description_data['0']['description'] = '';
                // }
                
            }
        }

        return $product_description_data;
    }


    public function getProductDescriptions2($product_id){
        $product_description_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_description WHERE product_id = '" . (int) $product_id . "'");

        foreach ($query->rows as $result) {
            //$desc = iconv("UTF-8","UTF-8//IGNORE", $result['description']);

            $product_description_data[$result['language_id']] = [

                 'name' =>$result['name'],
                 'description' => '',
                 'meta_title' => $result['meta_title'],
                 'meta_description' => '',
                 'meta_keyword' => $result['meta_keyword'],
                 'tag' => $result['tag'],
            ];

        }

        return $product_description_data;
    }

    public function getProductFilters($product_id)
    {
        $product_filter_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_filter WHERE product_id = '" . (int) $product_id . "'");

        foreach ($query->rows as $result) {
            $product_filter_data[] = $result['filter_id'];
        }

        return $product_filter_data;
    }

    public function getProductRewards($product_id)
    {
        $product_reward_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_reward WHERE product_id = '" . (int) $product_id . "'");

        foreach ($query->rows as $result) {
            $product_reward_data[$result['customer_group_id']] = ['points' => $result['points']];
        }

        return $product_reward_data;
    }

    public function getProductCategories($product_id)
    {
        $product_category_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_to_category WHERE product_id = '" . (int) $product_id . "'");

        foreach ($query->rows as $result) {
            $product_category_data[] = $result['category_id'];
        }

        return $product_category_data;
    }

    public function getProductDownloads($product_id)
    {
        $product_download_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_to_download WHERE product_id = '" . (int) $product_id . "'");

        foreach ($query->rows as $result) {
            $product_download_data[] = $result['download_id'];
        }

        return $product_download_data;
    }

    public function getProductStores($product_id)
    {
        $product_store_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_to_store WHERE product_id = '" . (int) $product_id . "'");

        foreach ($query->rows as $result) {
            $product_store_data[] = $result['store_id'];
        }

        return $product_store_data;
    }

    public function getProductLayouts($product_id)
    {
        $product_layout_data = [];

        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int) $product_id . "'");

        foreach ($query->rows as $result) {
            $product_layout_data[$result['store_id']] = $result['layout_id'];
        }

        return $product_layout_data;
    }

    public function getRecurrings($product_id)
    {
        $query = $this->db->query('SELECT * FROM `' . DB_PREFIX . "product_recurring` WHERE product_id = '" . (int) $product_id . "'");

        return $query->rows;
    }
    
    public function getExtraField($product_id)
    {
        $result = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_extra_field') . "' " );
            if($result->num_rows == 1) {       
                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_extra_field WHERE product_id = '" . $product_id ."' ");
            foreach ($query->rows as $result) {
                $extra_field = $result['extra_field'];
                return $extra_field;
                }
            }
        return "";

    }

    public function getShortDescription($product_id)
   {
        $result = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_mmos_shortdescr') . "' " );
            if($result->num_rows == 1) {       
                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_mmos_shortdescr WHERE product_id = '" . $product_id ."' ");
            foreach ($query->rows as $result) {
                $short_description = $result['description'];
                return $short_description;
                }
            }
        return "";

    }
    
    public function getProductConfigGroup()
    {
        $data = [];
        $resultData = [];
        
        $result = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_configurator_group') . "' " );
        if($result->num_rows > 0) {       
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_group ");
        }
        else
        {
            return '';
        }
        foreach ($query->rows as $result) {
            $data['product_configurator_group_id'] = $result['product_configurator_group_id'];
            $data['bottom'] = $result['bottom'];
            $data['sort_order'] = $result['sort_order'];
            $data['status'] = $result['status'];
            $config_categories = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_configurator_group_category LEFT JOIN " . DB_PREFIX . "product_configurator_category ON " . DB_PREFIX . "product_configurator_group_category.product_configurator_category_id = " . DB_PREFIX . "product_configurator_category.product_configurator_category_id WHERE product_configurator_group_id = '".$data['product_configurator_group_id']."' ");
            //$data['categories'] = $config_categories->rows;
            $data['categories'] = [];
            foreach($config_categories->rows as $result2)
            {

                array_push($data['categories'], $result2['category_id']);
            }
            $groupDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_group_description WHERE product_configurator_group_id = '".$data['product_configurator_group_id']."' ");
            $groupDetails = $groupDetails->row;
            $data['language_id'] = $groupDetails['language_id'];
            $data['title'] = $groupDetails['title'];
            $data['description'] = $groupDetails['description'];
            $data['meta_title'] = $groupDetails['meta_title'];
            $data['meta_description'] = $groupDetails['meta_description'];
            $data['meta_keyword'] = $groupDetails['meta_keyword'];
            $groupDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_group_to_layout WHERE product_configurator_group_id = '".$data['product_configurator_group_id']."' ");
            $groupDetails = $groupDetails->row;
            $data['store_id'] = $groupDetails['store_id'];
            $data['layout_id'] = $groupDetails['layout_id'];
            array_push($resultData,$data);
            
        }
        return $resultData;
    }
    
    public function getProductConfigProducts()
    {
        $data = [];
        $data['products'] = [];
        $resultData = [];
        $result = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_product_configurator_product') . "' " );
        if($result->num_rows > 0) {   
            $query = $this->db->query("SELECT DISTINCT product_id FROM " . DB_PREFIX . "product_product_configurator_product");
            //$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_product_configurator_product LEFT JOIN " . DB_PREFIX . "product ON " . DB_PREFIX . "product_product_configurator_product.product_id = " . DB_PREFIX . "product.product_id");
        }
        else
        {
            return '';
        }
        
        foreach($query->rows as $result)
        {
            $productDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = '".$result['product_id']."' ");
            $productDetails = $productDetails->row;
            $data['ean'] = $productDetails['ean'];
            $data['sku'] = $productDetails['sku'];
            $data['product_id'] = $result['product_id'];
            if(!is_null($productDetails['product_id']))
            {
                $data['found'] = true;
                $data['products'] = [];
                $subquery = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_product_configurator_product WHERE product_id = '".$result['product_id']."' ");
                foreach($subquery->rows as $result2)
                {
                    $data['group_id'] = $result2['product_configurator_group_id'];
                    
                    $subProductDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = '".$result2['product']."' ");
                    $subProductDetails = $subProductDetails->row;
                    $productData = [];
                    $categoryQuery = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_category WHERE product_configurator_category_id = '".$result2['product_configurator_category_id']."'" );
                    $categoryQuery = $categoryQuery->row; 
                    $productData['category'] = $categoryQuery['category_id'];
                    $productData['required'] = $categoryQuery['required'];
                    $productData['ean'] = $subProductDetails['ean'];
                    $productData['sku'] = $subProductDetails['sku'];
                    $productData['source_cat_config_id'] = $result2['product_configurator_category_id'];
                    array_push($data['products'],$productData);
                }
                array_push($resultData,$data);
            }
            //If ProductId is null
            else
            {
                $data['found'] = false;
                $data['group_id'] = null;
                $data['products'] = [];
                array_push($resultData,$data);
            }
        }
        return $resultData;
    }
    
    public function getProductConfigCategories()
    {
        $data = [];
        $data['products'] = [];
        $resultData = [];
        $categoryMaps = [];

        
        $result = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_configurator_category') . "' " );
        if($result->num_rows > 0) {       
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_category ");
        }
        else
        {
            return '';
        }
        
        
        foreach($query->rows as $result)
        {
            $data = [];
            $data['products'] = [];
        
            $data['product_configurator_category_id'] = $result['product_configurator_category_id'];
            $data['image'] = $result['image'];
            $data['parent_id'] = $result['parent_id'];
            $data['category_id'] = $result['category_id'];
            
            $data['show_product_image'] = $result['show_product_image'];
            $data['top'] = $result['top'];
            $data['column'] = $result['column'];
            $data['sort_order'] = $result['sort_order'];
            $data['status'] = $result['status'];
            $data['required'] = $result['required'];
            $data['date_added'] = $result['date_added'];
            $data['date_modified'] = $result['date_modified'];
            $categoryDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_category_description WHERE product_configurator_category_id = '".$data['product_configurator_category_id']."' ");
            $categoryDetails = $categoryDetails->row;
            $data['language_id'] = $categoryDetails['language_id'];
            $data['name'] = $categoryDetails['name'];
            $data['description'] = $categoryDetails['description'];
            $data['meta_title'] = $categoryDetails['meta_title'];
            $data['meta_description'] = $categoryDetails['meta_description'];
            $data['meta_keyword'] = $categoryDetails['meta_keyword'];
            $categoryDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_category_to_layout WHERE product_configurator_category_id = '".$data['product_configurator_category_id']."' ");
            $categoryDetails = $categoryDetails->row;
            $data['store_id'] = $categoryDetails['store_id'];
            $data['layout_id'] = $categoryDetails['layout_id'];
            $categoryDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_group_category WHERE product_configurator_category_id = '".$data['product_configurator_category_id']."' ");
            $categoryDetails = $categoryDetails->row;
            $data['group_id'] = $categoryDetails['product_configurator_group_id'];
            $categoryDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_category_path WHERE product_configurator_category_id = '".$data['product_configurator_category_id']."' AND level = 0");
            $categoryDetails = $categoryDetails->row;
            $data['path_id'] = $categoryDetails['path_id'];
            $data['level'] = 0;
            $categoryDetails = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurator_category_product LEFT JOIN " . DB_PREFIX . "product ON " . DB_PREFIX . "product_configurator_category_product.product_id = " . DB_PREFIX . "product.product_id WHERE product_configurator_category_id = '".$data['product_configurator_category_id']."' ");
            
            foreach($categoryDetails->rows as $resultprod)
            {
                $data_products = [];
                $data_products['ean'] = $resultprod['ean'];
                $data_products['sku'] = $resultprod['sku'];
                $data_products['sort_order'] = $resultprod['sort_order'];
                array_push($data['products'], $data_products);
            }
            array_push($resultData,$data);

        }
        return $resultData;
    }

    public function addProductConfiguratorGroups($groups)
    {


        foreach($groups as $group)
        {
            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_group (product_configurator_group_id, `bottom`, `sort_order`, `status`) VALUES ('" . (int) $group['group_id'] . "', '" . (int) $group['bottom'] . "', '" . $group['sort_order'] ."', '" . (int) $group['status'] . "')");    

            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_group_description (product_configurator_group_id, language_id, title, description, meta_title, meta_description, meta_keyword) VALUES ('" . (int) $group['group_id'] . "', '" . (int) $group['language_id'] . "', '" . $group['title'] ."', '" . $group['description'] ."', '" . $group['meta_title'] ."', '" . $group['meta_description'] ."', '" . $group['meta_keyword'] . "')");    

            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_group_to_layout (product_configurator_group_id, store_id, layout_id) VALUES ('" . (int) $group['group_id'] . "', '" . (int) $group['store_id'] . "', '" . (int) $group['layout_id'] . "')"); 

            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_group_to_store (product_configurator_group_id, store_id) VALUES ('" . (int) $group['group_id'] . "', '" . (int) $group['store_id'] . "')");            
        }

        return ['1','2','3','4'];
    }
    
    public function addProductConfiguratorCategories($categories, $setting_id)
    {
        $empty = "";
        $date = date('Y-m-d H:i:s');
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_group");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_group_description");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_group_to_layout");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_group_to_store");

        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_category");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_category_description");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_category_path");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_category_to_store");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_category_to_layout");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_group_category");
        $this->db->query('DELETE FROM ' . DB_PREFIX . "product_configurator_category_product");

        $categories2 = [];
        $catMappings = [];
        $catMappings[0] = 0;
        foreach ($categories as $category)
        {
            if($category['mapped_id'] == 0)
            {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_category (`image`, parent_id, show_product_image, `top`, `column`, `sort_order`, `status`, `required`, `date_added`, `date_modified`) VALUES ('" . $empty . "', '" . (int) $category['parent_id'] . "', '" . (int) $category['show_product_image'] ."', '" . (int) $category['top'] ."', '" . (int) $category['column'] ."', '" . (int) $category['sort_order'] ."', '" . (int) $category['status'] ."', '" . (int) $category['required'] ."', '" . $date ."', '" .$date . "')");    

            }
            else
            {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_category (`image`, parent_id, category_id, show_product_image, `top`, `column`, `sort_order`, `status`, `required`, `date_added`, `date_modified`) VALUES ('" . $empty . "', '" . (int) $category['parent_id'] . "', '" . (int) $category['mapped_id'] ."', '" . (int) $category['show_product_image'] ."', '" . (int) $category['top'] ."', '" . (int) $category['column'] ."', '" . (int) $category['sort_order'] ."', '" . (int) $category['status'] ."', '" . (int) $category['required'] ."', '" . $date ."', '" .$date . "')");    
            }

            $id = $this->db->getLastId();
            
            $category['target_config_id'] = (int) $id;
            $catMappings[$category['source_config_id']] = $category['target_config_id'];

            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_category_description (product_configurator_category_id, language_id, name, description, meta_title, meta_description, meta_keyword) VALUES ('" . (int) $id . "', '" . (int) $category['language_id'] . "', '" . $category['name'] ."', '" . $category['description'] ."', '" . $category['meta_title'] ."', '" . $category['meta_description'] ."', '" . $category['meta_keyword'] . "')");    

            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_category_path (product_configurator_category_id, path_id, level) VALUES ('" . (int) $id . "', '" . (int) $category['path_id'] . "', '" . $category['level'] ."' )");  
    
            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_category_to_store (product_configurator_category_id, store_id) VALUES ('" . (int) $id . "', '" . $category['store_id'] ."' )");  

            $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_category_to_layout (product_configurator_category_id, store_id, layout_id) VALUES ('" . (int) $id . "', '" . $category['store_id'] ."', '" . $category['layout_id'] ."' )");  
            
            if($category['group_id'] != 0)
            {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_group_category (product_configurator_category_id, product_configurator_group_id) VALUES ('" . (int) $id . "', '" . $category['group_id'] ."' )");  
            }
            foreach($category['products'] as $product)
            {
                $subQuery = $this->db->query('SELECT * FROM ' .DB_PREFIX . 'product WHERE '.$setting_id." = '".$product."' ");
                $subQuery = $subQuery->row;
                if(!is_null($subQuery))
                {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_category_product (product_configurator_category_id, product_id, sort_order) VALUES ('" . (int) $id . "', '" . $subQuery['product_id'] ."', '".(int) $category['sort_order']."' )");  
                }
            }
            array_push($categories2,$category);       
        }

        foreach ($categories2 as $category)
        {

            $category['parent_id'] = $catMappings[$category['parent_id']];
            $this->db->query('UPDATE ' . DB_PREFIX . "product_configurator_category SET parent_id = '".(int) $category['parent_id']."' WHERE product_configurator_category_id = '".$category['target_config_id']."'");

            $retardedCheck = 0;
            if(isset($category['mapped_id']) && $category['mapped_id'] != 0)
            {
                $retardedCheck = 1;
            }
            if($category['parent_id'] != 0 && $retardedCheck == 1)
            {
                
                $level = 1;
                $this->db->query('UPDATE ' . DB_PREFIX . "product_configurator_category_path SET path_id = '".(int) $category['parent_id']."' WHERE product_configurator_category_id = '".$category['target_config_id']."'");    
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_configurator_category_path (product_configurator_category_id, path_id, level) VALUES ('" . (int) $category['target_config_id'] . "', '" . $category['target_config_id'] ."', '".(int) $level."' )");  
            }
            else
            {
                $this->db->query('UPDATE ' . DB_PREFIX . "product_configurator_category_path SET path_id = '".(int) $category['target_config_id']."' WHERE product_configurator_category_id = '".$category['target_config_id']."'");    
            }                             
        }
        return [$catMappings];
    }

    public function addProductConfiguratorProducts($products, $setting_id, $fromId, $catMaps)
    {
        $ids = [];
        foreach($products as $product)
        {   
            $id = $this->db->query('SELECT * FROM ' .DB_PREFIX . 'product WHERE '.$setting_id." = '".$product[$fromId]."' ");
            $id = $id->row;
            array_push($ids,$id);
            $product_price = 0;
            if(!empty($id))
            {
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_product_configurator_product WHERE product_id = '".$id['product_id']."'");
                $this->db->query('DELETE FROM ' . DB_PREFIX . "product_product_configurator_group WHERE product_id = '".$id['product_id']."'");
                $this->db->query('INSERT INTO ' . DB_PREFIX . "product_product_configurator_group (product_id, product_configurator_group_id) VALUES ('" . $id['product_id'] . "', '" . $product['group'] ."' )");     
            
                foreach($product['subproducts'] as $subproduct)
                {
                      $subProductSearch = $this->db->query('SELECT * FROM ' .DB_PREFIX . 'product WHERE '.$setting_id." = '".$subproduct[$fromId]."' ");
                    if(!is_null($subProductSearch->row))
                    {
                        $subProductSearch = $subProductSearch->row;
                        $product_price = $product_price + $subProductSearch['price'];
                        $subproductId = $subProductSearch['product_id'];
                        $this->db->query('INSERT INTO ' . DB_PREFIX . "product_product_configurator_product (product_id, product_configurator_group_id, product_configurator_category_id, product) VALUES ('" . $id['product_id'] . "', '" . $product['group'] ."', '" . $catMaps[0][$subproduct['source_cat_config_id']] ."', '" . $subproductId ."' )");       
                    }               
                } 
                if ($product_price != 0)
                {
                    $this->db->query('UPDATE ' . DB_PREFIX . "product SET price = '".(float) $product_price."' WHERE product_id = '".$id['product_id']."'");    
                }
            }
        }
        return $ids;
    }
    
    
    public function insertExtraField($item, $product_id, $textField)
    {
        if(isset($item['expected_date']))
        {
                $textField =$item['expected_date'];
        }
        $lang_id = 1;

        if ($result = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_extra_field') . "' " )) 
        {
            if($result->num_rows == 1) 
            {
            
                $result2 = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_extra_field WHERE product_id = '" . $product_id ."' ");
                if( ($result2->num_rows) > 0)
                {
                    $this->db->query('UPDATE ' . DB_PREFIX . "product_extra_field SET extra_field = '" . $textField . "' WHERE product_id = '" . $product_id . "' " );
                }
                else
                {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_extra_field (product_id, language_id, extra_field) VALUES ('" . (int) $product_id . "', '" . (int) $lang_id . "', '" . $textField ."')");       
                }
            }
        }
    }

    public function insertShortDescription($item, $product_id)
    {
        if(isset($item['short_description']))
        {
            $description = $item['short_description'];       
            $lang_id = 1;
            $result = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_mmos_shortdescr') . "' " );
      
            if($result->num_rows == 1) 
            {           
                $result2 = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_mmos_shortdescr WHERE product_id = '" . $product_id ."' ");
                if( ($result2->num_rows) > 0)
                {
                    $this->db->query('UPDATE ' . DB_PREFIX . "product_mmos_shortdescr SET description = '" . $description . "' WHERE product_id = '" . $product_id . "' " );
                }
                else
                {
                    $this->db->query('INSERT INTO ' . DB_PREFIX . "product_mmos_shortdescr (product_id, language_id, description) VALUES ('" . (int) $product_id . "', '" . (int) $lang_id . "', '" . $description ."')");       
                }
            }
        }
    }


    ////V2-Specific



    public function getProductIds(){
        $product_ids = [];
        
        $query = $this->db->query('SELECT product_id FROM ' . DB_PREFIX . 'product WHERE status = 1');
        foreach($query->rows as $row){
            $product_ids[] = (int) $row['product_id'];
        }

        return $product_ids;
    }

    public function getProductsFromIDs($product_ids){
        $result_products = [];
        $product_ids = $this->escapeAndImplode($product_ids);

        $products = $this->v2getProducts($product_ids);
        $product_attributes = $this->v2getAttributes($product_ids);
        $product_categories = $this->v2getCategories($product_ids);
        $product_images = $this->v2getImages($product_ids);
        
        foreach($products as $product){
            $id = $product['product_id'];
            $result_products[$id] = $product; 
            $result_products[$id]['attributes'] = [];
            $result_products[$id]['categories'] = [];
            $result_products[$id]['images'] = [];
        }

        foreach($product_categories as $category){
            $id = $category['product_id'];
            if(!empty($result_products[$id])){
                $result_products[$id]['categories'][] = $category['category_id'];
            }
        }

        foreach($product_attributes as $attribute){
            $id = $attribute['product_id'];
            if(!empty($result_products[$id])){
                if(!empty($attribute['attribute_group_name']) && !empty($attribute['attribute_name']) && !empty($attribute['attribute_value'])){
                    $result_products[$id]['attributes'][] = 
                    [
                        'group' => $attribute['attribute_group_name'],
                        'name' => $attribute['attribute_name'],
                        'value' => $attribute['attribute_value']
                    ];
                }
            }
        }

        foreach($product_images as $image){
            $id = $attribute['product_id'];
            if(!empty($result_products[$id])){
                if(!empty($image['image'])){
                    $result_products[$id]['images'][] = $image['image'];
                }
            }
        }

        return $result_products;
        // $attributes_query = '';
    }

    private function escapeAndImplode($array){
        foreach($array as &$element){
            $element = "'" . $this->db->escape($element) . "'";
        }

        $result = implode(', ', $array);
        return $result;
    }    

    private function _table($table_name, $alias = null){
        $table_name = DB_PREFIX . $table_name;
        if(!empty($alias)){
            $table_name .= ' ' . $alias;
        }

        return $table_name;
    }    

    private function v2getProducts($product_ids){
        $language_id = (int) $this->config->get('config_language_id');

        $select_fields = 
        ['p.product_id', 'p.model', 'p.sku', 'p.ean', 'p.quantity', 'p.image as main_image', 'p.manufacturer_id', 'p.price', 
        'pd.name', 'pd.description', 'pd.meta_title', 'pd.meta_description', 'pd.meta_keyword', 'pd.tag'];

        $left_join_query = '';
        $this->joinExtraField($left_join_query, $select_fields);
        $this->joinShortDescription($left_join_query, $select_fields);

        $select_fields = implode(', ', $select_fields);

        $products_query = $this->db->query(
            'SELECT ' . $select_fields . ' FROM ' 
            . $this->_table('product') . ' p
            JOIN ' . $this->_table('product_description') . ' pd ON p.product_id = pd.product_id ' .$left_join_query. ' 

            WHERE p.product_id IN ( ' . $product_ids . ' ) AND p.status = 1 AND pd.language_id = ' .$language_id);
        
        return $products_query->rows;
    }

    private function v2getAttributes($product_ids){
        $language_id = (int) $this->config->get('config_language_id');
        $query = $this->db->query(
            'SELECT pa.attribute_id, pa.product_id, pa.text as attribute_value, attrgd.name as attribute_group_name, attrd.name as attribute_name 
            FROM '. $this->_table('product_attribute', 'pa') . ' 
            JOIN '.$this->_table('attribute', 'attr').' ON pa.attribute_id = attr.attribute_id 
            JOIN '.$this->_table('attribute_description', 'attrd').' ON pa.attribute_id = attrd.attribute_id AND pa.language_id = attrd.language_id 
            JOIN '.$this->_table('attribute_group_description', 'attrgd').' ON attr.attribute_group_id = attrgd.attribute_group_id AND pa.language_id = attrgd.language_id 
            WHERE pa.product_id IN ( ' . $product_ids . ' ) AND pa.language_id = '.$language_id
        );

        return $query->rows ?? [];
    }

    private function v2getCategories($product_ids){
        $language_id = (int) $this->config->get('config_language_id');
        $query = $this->db->query(
            'SELECT pc.category_id, pc.product_id FROM '. $this->_table('product_to_category', 'pc') .
            ' JOIN '.$this->_table('category', 'c').' ON pc.category_id = c.category_id 
            WHERE c.status = 1 AND pc.product_id IN ( ' . $product_ids . ' )'
        );

        return $query->rows ?? [];
    }

    private function v2getImages($product_ids){
        $query = $this->db->query(
            'SELECT pi.product_id, pi.image FROM '. $this->_table('product_image', 'pi') .' 
            WHERE pi.product_id IN ( ' . $product_ids . ' ) ORDER BY pi.product_id, pi.sort_order ASC'
        );

        return $query->rows ?? [];
    }

    //At some point huh
    // private function joinTable($table_name, $table_fields, $table_alias, &$query, &$select_fields){
    //     $table_exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table_name) . "' " );
    //     if($table_exists->num_rows < 1){
    //         return;
    //     }

    //     $query .= 'LEFT JOIN ' . $this->_table('product_mmos_shortdescr', $alias) . ' ON '.$alias.'.product_id = p.product_id AND '.$alias.'.language_id = pd.language_id ';
    // }

    private function addCatalogToImage($image){
        $catalog = substr($image, 0, 7);
        if ($catalog != "catalog"){
            $image = "catalog/" . $image;
        }

        return $image;
    }

    private function joinExtraField(&$query, &$fields, $alias = 'pextra'){
        $table_exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_extra_field') . "' " );
        if($table_exists->num_rows < 1){
            return;
        }

        $query .= 'LEFT JOIN ' . $this->_table('product_extra_field', $alias) . ' ON '.$alias.'.product_id = p.product_id AND '.$alias.'.language_id = pd.language_id ';
        array_push($fields, $alias . '.extra_field');
        return;
    }

    private function joinShortDescription(&$query, &$fields, $alias = 'pdshort'){
        $table_exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . 'product_mmos_shortdescr') . "' " );
        if($table_exists->num_rows < 1){
            return;
        }

        $query .= 'LEFT JOIN ' . $this->_table('product_mmos_shortdescr', $alias) . ' ON '.$alias.'.product_id = p.product_id AND '.$alias.'.language_id = pd.language_id ';
        array_push($fields, $alias . '.description as short_description');
        return;
    }

    private function saveImageToDir($image_content, $file_name, $is_webp = false){
        $result = [];
        if($is_webp){
            $webpImage = imagecreatefromwebp('data://image/webp;base64,' . base64_encode($image_content));
            $content_dir = DIR_IMAGE . '/' . $file_name;
            $result['img_added'] = imagejpeg($webpImage, $content_dir, 90);
            imagedestroy($webpImage);
        }
        else{
            file_put_contents(DIR_IMAGE . '/' . $file_name, $image_content);
        }

        return $result;
    }
}
