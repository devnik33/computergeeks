<?php

class ModelCatalogSkAttributes extends Model{

    public function getAttributes($data = []){
        $query = $this->db->query(
            'SELECT 
                attribute.attribute_id as attribute_id, 
                attribute_description.name as attribute_name,
                attribute_group.attribute_group_id as attribute_group_id,
                attribute_group.name as attribute_group_name
            FROM  '.DB_PREFIX.'attribute AS attribute 
            LEFT JOIN '.DB_PREFIX.'attribute_description AS attribute_description ON attribute.attribute_id = attribute_description.attribute_id 
            LEFT JOIN '.DB_PREFIX.'attribute_group_description AS attribute_group ON attribute.attribute_group_id = attribute_group.attribute_group_id'
        );

        $attribute_data = $query->rows;
        return $attribute_data;
    }

    public function syncFilters(){
        try{

                //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter");
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_value");
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_value_description");
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_description");
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_value_to_product");
            
            $results = [];

            $language_id = (int) $this->config->get('config_language_id');
			$flexi_filter_table_exists = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "tf_filter'");

			if(empty($flexi_filter_table_exists->rows)){
				return ['error' => 'Cannot find Flexi Filter OcMod'];
			}
			
            $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_value_to_product");


            $query = $this->db->query('SELECT * FROM '.DB_PREFIX.'attribute_description ');
            $products_to_add = [];
            
            $flexi_filter_attributes = $this->db->query("SELECT * FROM " . DB_PREFIX . "tf_filter");

            $flexi_filter_ids_to_filter_ids = [];

            $all_datas = [];
            foreach($flexi_filter_attributes->rows as $flexi_filter_row){
                $data = json_decode($flexi_filter_row['setting']);
                $data = (array) $data;
                
                $filter_attribute_id = $data['key_attribute'][0];

                $flexi_filter_ids_to_filter_ids[$filter_attribute_id] = $flexi_filter_row['filter_id'];  
            }

            foreach($query->rows as $attribute){
                $attribute_id = strval($attribute['attribute_id']);

                if(!empty($flexi_filter_ids_to_filter_ids[$attribute_id])){
                    $filter_id = $flexi_filter_ids_to_filter_ids[$attribute_id];

                    $results['existing'][] = ['attr_id' => $attribute_id, 'filt_id' => $filter_id, 'name' => $attribute['name']];

                }
                else{
                    $filter_values = [
                        'value_sync_status' => '1',
                        'collapse' => '0',
                        'input_type' => 'checkbox',
                        'list_type' => 'text',
                        'value_image_width' => '30',
                        'value_image_height' => '30',
                        'key_attribute' => [$attribute_id],
                        'key_product_name' => '0',
                        'key_product_description' => '0',
                        'key_product_tags' => '0'
                    ];  

                    $this->db->query("INSERT INTO " . DB_PREFIX . "tf_filter SET 
                        sort_order = '10', 
                        filter_language_id = ".$language_id.", 
                        status = '1', 
                        setting = '" . $this->db->escape(json_encode($filter_values)) . "', 
                        date_added = NOW(), 
                        date_modified = NOW()"
                    );  

                    $filter_id = $this->db->getLastId();

                    $filter_name = $attribute['name'];
                    $this->db->query("INSERT INTO " . DB_PREFIX . "tf_filter_description SET 
                        filter_id = '".$filter_id."', 
                        language_id = ".$language_id.", 
                        name = '".$filter_name. "'"
                    ); 

                    $results['new'][] = ['attr_id' => $attribute_id, 'filt_id' => $filter_id, 'name' => $filter_name];

                }

                $flexi_filter_values_to_value_ids = [];
                $flexi_filter_attribute_values = $this->db->query("SELECT * FROM " . DB_PREFIX . "tf_filter_value WHERE filter_id = '" . $filter_id . "'");
                foreach($flexi_filter_attribute_values as $value){
                    $flexi_filter_values_to_value_ids[$value['value']] = $value['value_id'];
                }
 
                $products = $this->db->query('SELECT * FROM '.DB_PREFIX.'product_attribute WHERE attribute_id = '.$attribute['attribute_id'].'');
				
                foreach($products->rows as $product){
                    $attribute_text = $product['text'];
                    $product_id = $product['product_id'];

                    if(empty($flexi_filter_values_to_value_ids[$attribute_text])){

                        $this->db->query("INSERT INTO " . DB_PREFIX . "tf_filter_value SET filter_id = '".$filter_id."', status = '1', sort_order = '0', value = '".$attribute_text. "'");

                        $valueId = $this->db->getLastId();

                        $this->db->query("INSERT INTO " . DB_PREFIX . "tf_filter_value_description SET value_id = '".$valueId."', language_id = ".$language_id.", name = '".$attribute_text. "'");

                        $flexi_filter_values_to_value_ids[$attribute_text] = $valueId;

                    }

                    array_push($products_to_add, ['value_id' => $flexi_filter_values_to_value_ids[$attribute_text], 'product_id' => $product_id]);
                }

            }
			

            foreach(array_chunk($products_to_add, 250) as $product_chunk){
                $sql = "INSERT INTO " . DB_PREFIX . "tf_filter_value_to_product(`value_id`, `product_id`) VALUES ";
                $first = true;

                foreach($product_chunk as $product_to_add){
                    if(!$first){
                        $sql .= ',';
                    }
                    $sql .= "(". $this->db->escape($product_to_add['value_id']) ."," . $this->db->escape($product_to_add['product_id']) .")";
                    $first = false;
                }
                $this->db->query($sql);
            }

        }
        catch(Exception $ex){
            return ['error' => 'ERROR'];
        }
		return  ['result' => $results];


    }
    // public function syncFiltersOLD(){
    //     try{
    //         $language_id = (int) $this->config->get('config_language_id');
	// 		$flexi_filter_table_exists = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "tf_filter'");
	// 		if(empty($flexi_filter_table_exists->rows)){
	// 			return ['error' => 'Cannot find Flexi Filter OcMod'];
	// 		}
			
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter");
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_value");
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_value_description");
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_description");
    //         $this->db->query("DELETE FROM " . DB_PREFIX . "tf_filter_value_to_product");


    //         $query = $this->db->query('SELECT * FROM '.DB_PREFIX.'attribute_description ');
    //         $products_to_add = [];
	// 		$attributes_count = 0;
	// 		$uniqueAttributeValues = 0;
	// 		$productAttributeValues = 0;

    //         $results = [];
    //         //{"value_sync_status":"1","collapse":"0","input_type":"checkbox","list_type":"text","value_image_width":"30","value_image_height":"30","key_attribute":["11614"],"key_product_name":"0","key_product_description":"0","key_product_tags":"0"}
            
    //         //Fetching Already Existing Filters
    //         $flexi_filter_attributes = $this->db->query("SELECT * FROM " . DB_PREFIX . "tf_filter");
    //         $flexi_filter_existing_attributes = [];
    //         foreach($flexi_filter_attributes->rows as $flexi_filter_row){
    //             $data = json_decode($flexi_filter_row['setting']);
    //             $filter_attribute_id = $data['key_attribute'][0];
    //             $flexi_filter_existing_attributes[] = $filter_attribute_id;
    //         }

    //         foreach($query->rows as $attribute){
    //             $attribute_id = strval($attribute['attribute_id']);

    //             if(in_array($attribute_id, $flexi_filter_existing_attributes)){
    //                 $results['existing'][] = ['id' => $attribute_id, 'name' => $attribute['name']];
    //                 continue;
    //             }

    //             $filter_values = [
    //                 'value_sync_status' => '1',
    //                 'collapse' => '0',
    //                 'input_type' => 'checkbox',
    //                 'list_type' => 'text',
    //                 'value_image_width' => '30',
    //                 'value_image_height' => '30',
    //                 'key_attribute' => [$attribute_id],
    //                 'key_product_name' => '0',
    //                 'key_product_description' => '0',
    //                 'key_product_tags' => '0'
    //             ];   

    //             $this->db->query("INSERT INTO " . DB_PREFIX . "tf_filter SET sort_order = '10', filter_language_id = ".$language_id.", status = '1', setting = '" . $this->db->escape(json_encode($filter_values)) . "', date_added = NOW(), date_modified = NOW()");                

    //             $filter_id = $this->db->getLastId();
    //             $filter_name = $attribute['name'];

    //             $this->db->query("INSERT INTO " . DB_PREFIX . "tf_filter_description SET filter_id = '".$filter_id."', language_id = ".$language_id.", name = '".$filter_name. "'");         
                
    //             $results['inserted'] = ['id' => $filter_id, 'name' => $filter_name];
                
    //             $products = $this->db->query('SELECT * FROM '.DB_PREFIX.'product_attribute WHERE attribute_id = '.$attribute['attribute_id'].'');
    //             $uniqueValues = [];
				
    //             foreach($products->rows as $product){
    //                 if(!isset($uniqueValues[$product['text']])){
    //                     $results['values'][] = ['id' => $filter_id,, 'value' => $product['text']];

    //                     $this->db->query("INSERT INTO " . DB_PREFIX . "tf_filter_value SET filter_id = '".$filter_id."', status = '1', sort_order = '0', value = '".$product['text']. "'");
    //                     $valueId = $this->db->getLastId();

    //                     $this->db->query("INSERT INTO " . DB_PREFIX . "tf_filter_value_description SET value_id = '".$valueId."', language_id = ".$language_id.", name = '".$product['text']. "'");

    //                     $uniqueValues[$product['text']] = $valueId;
	// 					$uniqueAttributeValues += 1;
    //                 }

    //                 array_push($products_to_add, ['value_id' => $uniqueValues[$product['text']], 'product_id' => $product['product_id']]);
	// 				$productAttributeValues += 1;
    //             }
    //         }
			
			

    //         foreach(array_chunk($products_to_add, 250) as $product_chunk){
    //             $sql = "INSERT INTO " . DB_PREFIX . "tf_filter_value_to_product(`value_id`, `product_id`) VALUES ";
    //             $first = true;
    //             foreach($product_chunk as $product_to_add){
    //                 if(!$first){
    //                     $sql .= ',';
    //                 }
    //                 $sql .= "(". $this->db->escape($product_to_add['value_id']) ."," . $this->db->escape($product_to_add['product_id']) .")";
    //                 $first = false;
    //             }
    //             $this->db->query($sql);
    //         }
    //     }
    //     catch(Exception $ex){
    //         return ['error' => $ex->getMessage() . $ex->getLine()];
    //     }
	// 	return  ['attributesNo' => $attributes_count, 'uniqueValuesNo' => $uniqueAttributeValues, 'productsToAttributesNo' => $productAttributeValues];
    // }
}
