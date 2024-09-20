<?php

class ModelCatalogSkManufacturer extends Model
{




    public function addManufacturer($data)
    {
        $this->db->query('INSERT INTO ' . DB_PREFIX . "manufacturer SET name = '" . $this->db->escape($data['name']) . "', sort_order = '" . (int) $data['sort_order'] . "'");

        $manufacturer_id = $this->db->getLastId();

        if (isset($data['image'])) {
            $this->db->query('UPDATE ' . DB_PREFIX . "manufacturer SET image = '" . $this->db->escape($data['image']) . "' WHERE manufacturer_id = '" . (int) $manufacturer_id . "'");
        }



        if (isset($data['manufacturer_store'])) {
            foreach ($data['manufacturer_store'] as $store_id) {
                $this->db->query('INSERT INTO ' . DB_PREFIX . "manufacturer_to_store SET manufacturer_id = '" . (int) $manufacturer_id . "', store_id = '" . (int) $store_id . "'");
            }
        }


        $this->cache->delete('manufacturer');

        return $manufacturer_id;
    }






    public function getManufacturer($manufacturer_id)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'manufacturer m LEFT JOIN ' . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id) WHERE m.manufacturer_id = '" . (int) $manufacturer_id . "' AND m2s.store_id = '" . (int) $this->config->get('config_store_id') . "'");

        return $query->row;
    }

    public function getManufacturers($data = [])
    {
        if ($data) {
            $sql = 'SELECT * FROM ' . DB_PREFIX . 'manufacturer m LEFT JOIN ' . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id) WHERE m2s.store_id = '" . (int) $this->config->get('config_store_id') . "'";

            $sort_data = [
                'name',
                'sort_order',
            ];

            if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
                $sql .= ' ORDER BY ' . $data['sort'];
            } else {
                $sql .= ' ORDER BY name';
            }

            if (isset($data['order']) && ('DESC' == $data['order'])) {
                $sql .= ' DESC';
            } else {
                $sql .= ' ASC';
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

            $query = $this->db->query($sql);

            return $query->rows;
        }
        $manufacturer_data = $this->cache->get('manufacturer.' . (int) $this->config->get('config_store_id'));

        if (!$manufacturer_data) {
            $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'manufacturer m LEFT JOIN ' . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id) WHERE m2s.store_id = '" . (int) $this->config->get('config_store_id') . "' ORDER BY name");

            $manufacturer_data = $query->rows;

            $this->cache->set('manufacturer.' . (int) $this->config->get('config_store_id'), $manufacturer_data);
        }

        return $manufacturer_data;
    }


    public function getManufacturerByName($manufacturerName)
    {
        $query = $this->db->query('
			SELECT 
				DISTINCT m.manufacturer_id 
			FROM ' . DB_PREFIX . 'manufacturer m 
				LEFT JOIN ' . DB_PREFIX . "manufacturer_to_store m2 ON (m.manufacturer_id = m2.manufacturer_id) 
			WHERE 
				m.name = '" . $manufacturerName . "' 
				AND m2.store_id = '" . (int) $this->config->get('config_store_id') . "' 
		");

        return $query->row;
    }
}
