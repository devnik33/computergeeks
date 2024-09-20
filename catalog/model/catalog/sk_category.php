<?php

class ModelCatalogSkCategory extends Model
{
    public function updateParentId($data, $parent_id)
    {
        $level = 1;
        $prevLevel = 0;
        $this->db->query('UPDATE '.DB_PREFIX."category SET parent_id = '".(int) $parent_id."' WHERE category_id = '".(int) $data['category_id']."'");
        
        $this->db->query('UPDATE '.DB_PREFIX."category_path SET level = '".(int) $level."' WHERE category_id = '".(int) $data['category_id']."' AND path_id = '".(int) $data['category_id']."' ");
        
        $findParent = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_path WHERE category_id = '" . (int) $data['category_id'] ."' AND level = '".$prevLevel."'");
        if( ($findParent->num_rows) > 0)
        {
            $this->db->query('UPDATE '.DB_PREFIX."category_path SET path_id = '".(int) $parent_id."' WHERE category_id = '".(int) $data['category_id']."' AND level = '".$prevLevel."' ");
        }
        else
        {
            $this->db->query('INSERT INTO '.DB_PREFIX."category_path SET category_id = '".(int) $data['category_id']."', path_id = '".(int) $parent_id."', level = '".$prevLevel."'");
        }
    }

    //Returns url to save in DB

    public function addCategory($data){
        $this->db->query('INSERT INTO '.DB_PREFIX."category SET parent_id = '".(int) $data['parent_id']."', `top` = '".(isset($data['top']) ? (int) $data['top'] : 1)."', `column` = '".(int) $data['column']."', sort_order = '".(int) $data['sort_order']."', status = '".(int) $data['status']."', date_modified = NOW(), date_added = NOW()");

        $category_id = $this->db->getLastId();

        if (isset($data['image']) and !empty($data['image'])) {

            $image_url = $data['imageUrl'];
            $image = $data['image'];
            $saved_image_path = $this->insertCategoryImage($image_url, $image);
            
            $this->db->query('UPDATE '.DB_PREFIX."category SET image = '".$this->db->escape($saved_image_path)."' WHERE category_id = '".(int) $category_id."'");
        }

        $language_id = (int) $this->config->get('config_language_id');
        foreach ($data['category_description'] as $key => $value) {
            $this->db->query('INSERT INTO '.DB_PREFIX."category_description SET category_id = '".(int) $category_id."', language_id = '".(int) $language_id."', name = '".$this->db->escape($value['name'])."', description = '".$this->db->escape($value['description'])."', meta_title = '".$this->db->escape($value['meta_title'])."', meta_description = '".$this->db->escape($value['meta_description'])."', meta_keyword = '".$this->db->escape($value['meta_keyword'])."'");
        }

        // MySQL Hierarchical Data Closure Table Pattern
        $level = 0;

        $query = $this->db->query('SELECT * FROM `'.DB_PREFIX."category_path` WHERE category_id = '".(int) $data['parent_id']."' ORDER BY `level` ASC");

        foreach ($query->rows as $result) {
            $this->db->query('INSERT INTO `'.DB_PREFIX."category_path` SET `category_id` = '".(int) $category_id."', `path_id` = '".(int) $result['path_id']."', `level` = '".(int) $level."'");

            ++$level;
        }

        $this->db->query('INSERT INTO `'.DB_PREFIX."category_path` SET `category_id` = '".(int) $category_id."', `path_id` = '".(int) $category_id."', `level` = '".(int) $level."'");

        if (isset($data['category_filter'])) {
            foreach ($data['category_filter'] as $filter_id) {
                $this->db->query('INSERT INTO '.DB_PREFIX."category_filter SET category_id = '".(int) $category_id."', filter_id = '".(int) $filter_id."'");
            }
        }

        if (isset($data['category_store'])) {
            foreach ($data['category_store'] as $store_id) {
                $this->db->query('INSERT INTO '.DB_PREFIX."category_to_store SET category_id = '".(int) $category_id."', store_id = '".(int) $store_id."'");
            }
        }

        if (isset($data['category_seo_url'])) {
            foreach ($data['category_seo_url'] as $store_id => $language) {
                foreach ($language as $language_id => $keyword) {
                    if (!empty($keyword)) {
                        $this->db->query('INSERT INTO '.DB_PREFIX."seo_url SET store_id = '".(int) $store_id."', language_id = '".(int) $language_id."', query = 'category_id=".(int) $category_id."', keyword = '".$this->db->escape($keyword)."'");
                    }
                }
            }
        }

        // Set which layout to use with this category
        if (isset($data['category_layout'])) {
            foreach ($data['category_layout'] as $store_id => $layout_id) {
                $this->db->query('INSERT INTO '.DB_PREFIX."category_to_layout SET category_id = '".(int) $category_id."', store_id = '".(int) $store_id."', layout_id = '".(int) $layout_id."'");
            }
        }

        $this->cache->delete('category');

        return $category_id;
    }

    private function insertCategoryImage($image_dir, $image){

        $sanitized_filename = preg_replace('/[^a-zA-Z0-9\-\._]/', '', basename($image));

        $new_filename = dirname($image) . '/' . $sanitized_filename;

        $content = fopen($image_dir . '/' . str_replace(' ', '%20', $image), 'r');

        if (!is_dir(DIR_IMAGE . '/' . dirname($image))) {
            mkdir(DIR_IMAGE . '/' . dirname($image), 0777, true);
        }

        file_put_contents(DIR_IMAGE . '/' . $new_filename, $content);

        return $new_filename;
    }

    private function updateCategoryImage($category_id, $image_dir, $image, $save = true){
        $saved_image_path = $this->insertCategoryImage($image_dir, $image);
        $this->db->query('UPDATE '.DB_PREFIX."category SET image = '".$this->db->escape($saved_image_path)."' WHERE category_id = '". (int) $category_id ."'");
        return $saved_image_path;

    }


    public function synchronizeCategory($category){
        $result = [];
        $category_id = (int) $category['category_id'];
        $image_dir = $category['image_dir'] ?? '';
        $image_url = $category['image_url'] ?? '';

        $db_category = $this->getCategory($category_id);

        if(empty($db_category)){
            $result['error'] = 'Category with ID: ' . $category_id . ' does not exist in Target Store.';
            $result['data'] = ['category_id' => $category_id, 'image_dir' => $image_dir, 'image_url' => $image_url];
            return $result;
        }

        if(empty($image_dir) || empty($image_url)){
            $result['error'] = 'Image field empty for category: ' . $category_id . ' !';
            $result['data'] = ['category_id' => $category_id, 'image_dir' => $image_dir, 'image_url' => $image_url];
            return $result;
        }

        $image_path = $this->updateCategoryImage($category_id, $image_dir, $image_url);
        $result['result'] = 'Updated Image for Category: ' . $category_id;
        $result['data'] = ['category_id' => $category_id, 'image_dir' => $image_dir, 'image_url' => $image_url];
        $result['imagepath'] = $image_path;
        
        return $result;
    }

    public function getCategory($category_id)
    {
        $query = $this->db->query('SELECT DISTINCT * FROM '.DB_PREFIX.'category c LEFT JOIN '.DB_PREFIX.'category_description cd ON (c.category_id = cd.category_id) LEFT JOIN '.DB_PREFIX."category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.category_id = '".(int) $category_id."' AND cd.language_id = '".(int) $this->config->get('config_language_id')."' AND c2s.store_id = '".(int) $this->config->get('config_store_id')."' AND c.status = '1'");

        return $query->row;
    }

    public function getCategoryByName($categoryName)
    {
        $query = $this->db->query('
			SELECT 
				DISTINCT * FROM '.DB_PREFIX.'category c 
				LEFT JOIN '.DB_PREFIX.'category_description cd ON (c.category_id = cd.category_id) 
				LEFT JOIN '.DB_PREFIX."category_to_store c2s ON (c.category_id = c2s.category_id) 
			WHERE 
				cd.name = '".$categoryName."' 
				AND cd.language_id = '".(int) $this->config->get('config_language_id')."' 
				AND c2s.store_id = '".(int) $this->config->get('config_store_id')."' 
				AND c.status = '1'
		");

        return $query->row;
    }
    
    public function getCategoryWithParent($categoryName, $parent_id)
    {
        $query = $this->db->query('
			SELECT 
				DISTINCT * FROM '.DB_PREFIX.'category c 
				LEFT JOIN '.DB_PREFIX.'category_description cd ON (c.category_id = cd.category_id) 
				LEFT JOIN '.DB_PREFIX."category_to_store c2s ON (c.category_id = c2s.category_id) 
			WHERE 
				cd.name = '".$categoryName."' 
				AND cd.language_id = '".(int) $this->config->get('config_language_id')."' 
				AND c2s.store_id = '".(int) $this->config->get('config_store_id')."' 
				AND c.status = '1' AND c.parent_id = '".(int) $parent_id."'
		");

        return $query->row;
    }
    
    public function getCategoryByName2($categoryName, $parentId)
    {
        $query = $this->db->query('
			SELECT 
				DISTINCT * FROM '.DB_PREFIX.'category c 
				LEFT JOIN '.DB_PREFIX.'category_description cd ON (c.category_id = cd.category_id) 
				LEFT JOIN '.DB_PREFIX."category_to_store c2s ON (c.category_id = c2s.category_id) 
			WHERE 
				cd.name = '".$categoryName."' 
				AND cd.language_id = '".(int) $this->config->get('config_language_id')."' 
				AND c2s.store_id = '".(int) $this->config->get('config_store_id')."' 
				AND c.status = '1' AND c.parent_id = '".(int) $parentId."'
		");

        return $query->row;
    }

    public function verifyCategoryExists($category_id, $parent_id){
        $query = $this->db->query('
        SELECT 
            DISTINCT * FROM '.DB_PREFIX.'category c 
            LEFT JOIN '.DB_PREFIX.'category_description cd ON (c.category_id = cd.category_id) 
            LEFT JOIN '.DB_PREFIX."category_to_store c2s ON (c.category_id = c2s.category_id) 
        WHERE 
            c.category_id = '".$category_id."' 
            AND cd.language_id = '".(int) $this->config->get('config_language_id')."' 
            AND c2s.store_id = '".(int) $this->config->get('config_store_id')."' 
            AND c.status = '1' AND c.parent_id = '".(int) $parent_id."'
        ");

        return $query->row;
    }

    public function getCategories($parent_id = 0)
    {
        $query = $this->db->query('SELECT * FROM '.DB_PREFIX.'category c LEFT JOIN '.DB_PREFIX.'category_description cd ON (c.category_id = cd.category_id) LEFT JOIN '.DB_PREFIX."category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '".(int) $parent_id."' AND cd.language_id = '".(int) $this->config->get('config_language_id')."' AND c2s.store_id = '".(int) $this->config->get('config_store_id')."'  AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)");

        return $query->rows;
    }

    public function getShistos()
    {
        $sql = "SELECT cp.category_id AS category_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR '  --  ') AS name, c1.parent_id, c1.sort_order, c1.image FROM ".DB_PREFIX.'category_path cp LEFT JOIN '.DB_PREFIX.'category c1 ON (cp.category_id = c1.category_id) LEFT JOIN '.DB_PREFIX.'category c2 ON (cp.path_id = c2.category_id) LEFT JOIN '.DB_PREFIX.'category_description cd1 ON (cp.path_id = cd1.category_id) LEFT JOIN '.DB_PREFIX."category_description cd2 ON (cp.category_id = cd2.category_id) WHERE cd1.language_id = '".(int) $this->config->get('config_language_id')."' AND cd2.language_id = '".(int) $this->config->get('config_language_id')."'";

        if (!empty($data['filter_name'])) {
            $sql .= " AND cd2.name LIKE '%".$this->db->escape($data['filter_name'])."%'";
        }

        $sql .= ' GROUP BY cp.category_id';

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getCategoryFilters($category_id)
    {
        $implode = [];

        $query = $this->db->query('SELECT filter_id FROM '.DB_PREFIX."category_filter WHERE category_id = '".(int) $category_id."'");

        foreach ($query->rows as $result) {
            $implode[] = (int) $result['filter_id'];
        }

        $filter_group_data = [];

        if ($implode) {
            $filter_group_query = $this->db->query('SELECT DISTINCT f.filter_group_id, fgd.name, fg.sort_order FROM '.DB_PREFIX.'filter f LEFT JOIN '.DB_PREFIX.'filter_group fg ON (f.filter_group_id = fg.filter_group_id) LEFT JOIN '.DB_PREFIX.'filter_group_description fgd ON (fg.filter_group_id = fgd.filter_group_id) WHERE f.filter_id IN ('.implode(',', $implode).") AND fgd.language_id = '".(int) $this->config->get('config_language_id')."' GROUP BY f.filter_group_id ORDER BY fg.sort_order, LCASE(fgd.name)");

            foreach ($filter_group_query->rows as $filter_group) {
                $filter_data = [];

                $filter_query = $this->db->query('SELECT DISTINCT f.filter_id, fd.name FROM '.DB_PREFIX.'filter f LEFT JOIN '.DB_PREFIX.'filter_description fd ON (f.filter_id = fd.filter_id) WHERE f.filter_id IN ('.implode(',', $implode).") AND f.filter_group_id = '".(int) $filter_group['filter_group_id']."' AND fd.language_id = '".(int) $this->config->get('config_language_id')."' ORDER BY f.sort_order, LCASE(fd.name)");

                foreach ($filter_query->rows as $filter) {
                    $filter_data[] = [
                        'filter_id' => $filter['filter_id'],
                        'name' => $filter['name'],
                    ];
                }

                if ($filter_data) {
                    $filter_group_data[] = [
                        'filter_group_id' => $filter_group['filter_group_id'],
                        'name' => $filter_group['name'],
                        'filter' => $filter_data,
                    ];
                }
            }
        }

        return $filter_group_data;
    }

    public function getCategoryLayoutId($category_id)
    {
        $query = $this->db->query('SELECT * FROM '.DB_PREFIX."category_to_layout WHERE category_id = '".(int) $category_id."' AND store_id = '".(int) $this->config->get('config_store_id')."'");

        if ($query->num_rows) {
            return (int) $query->row['layout_id'];
        }

        return 0;
    }

    public function getTotalCategoriesByCategoryId($parent_id = 0)
    {
        $query = $this->db->query('SELECT COUNT(*) AS total FROM '.DB_PREFIX.'category c LEFT JOIN '.DB_PREFIX."category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '".(int) $parent_id."' AND c2s.store_id = '".(int) $this->config->get('config_store_id')."' AND c.status = '1'");

        return $query->row['total'];
    }

    public function getProductConfiguratorData()
    {
        return "Test";
    }


    public function addFullCategoryNew($data, $mappings)
    {
        $this->db->query('INSERT INTO '.DB_PREFIX."category SET parent_id = '".(int) $data['parent_id']."', `top` = '1', `column` = '1', sort_order = '0', status = '1', date_modified = NOW(), date_added = NOW()");
        $config_store_id = $this->config->get('config_store_id');
        $category_id = $this->db->getLastId();

        $mappings[$data['category_id']] = $category_id;

        if (isset($data['category_image']) and !empty($data['category_image'])) 
        {
            if(isset($data['category_image_base_url']) && $data['category_image_base_url'] != ''){
                $data['category_image'] = $data['category_image_base_url'] . '/' . $data['category_image'];
            } 

            $sanitizedFilename = preg_replace('/[^a-zA-Z0-9\-\._]/', '', basename($data['category_image']));
            $newFilename = dirname($data['category_image']) . '/' . $sanitizedFilename;

            $content = fopen( str_replace(' ', '%20', $data['category_image']), 'r');

            if (!is_dir(DIR_IMAGE . '/' . dirname($data['category_image']))) {
                mkdir(DIR_IMAGE . '/' . dirname($data['category_image']), 0777, true);
            }

            file_put_contents(DIR_IMAGE . '/' . $newFilename, $content);
            
            $this->db->query('UPDATE '.DB_PREFIX."category SET image = '".$this->db->escape($newFilename)."' WHERE category_id = '".(int) $category_id."'");
        }

        $language_id = (int) $this->config->get('config_language_id');

        //Category Description
        $this->db->query('INSERT INTO '.DB_PREFIX."category_description SET category_id = '".(int) $category_id."', language_id = '".(int) $language_id."', name = '".$this->db->escape($data['category_name'])."', description = '".$this->db->escape('')."', meta_title = '".$this->db->escape($data['category_name'])."', meta_description = '".$this->db->escape('')."', meta_keyword = '".$this->db->escape('')."'");

        //Category Path
        foreach($data['paths'] as $path){
            $this->db->query('INSERT INTO `'.DB_PREFIX."category_path` SET `category_id` = '".(int) $category_id."', `path_id` = '".(int) $mappings[$path['path_id']]."', `level` = '".(int) $path['level']."'");
        }

        //Category Store
        $this->db->query('INSERT INTO '.DB_PREFIX."category_to_store SET category_id = '".(int) $category_id."', store_id = '".$this->config->get('config_store_id')."'");

        //Category Layout
        $this->db->query('INSERT INTO '.DB_PREFIX."category_to_layout SET category_id = '".(int) $category_id."', store_id = '".(int) $config_store_id."', layout_id = '0'");


        $this->cache->delete('category');

        return $category_id;
    }
}
