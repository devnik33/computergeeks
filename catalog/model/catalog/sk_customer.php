<?php

class ModelCatalogSkCustomer extends Model {

	public function getExistingCustomers(){
		$query = $this->db->query('SELECT customer_id, email, telephone FROM ' . DB_PREFIX . 'customer');
		return $query->rows;
	}
	
	public function addCustomer($data) {
		$language_id = (int) $this->config->get('config_language_id');
		$store_id = (int) $this->config->get('config_store_id');


		$this->db->query("
			INSERT INTO " . DB_PREFIX . "customer SET 
			customer_group_id = '" . (int)$data['customer_group_id'] . "', 
			firstname = '" . $this->db->escape($data['firstname']) . "', 
			lastname = '" . $this->db->escape($data['lastname']) . "', 
			email = '" . $this->db->escape($data['email']) . "', 
			telephone = '" . $this->db->escape($data['telephone']) . "', 
			custom_field = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : json_encode(array())) . "', 
			newsletter = '" . (int)$data['newsletter'] . "', 
			salt = '" . $this->db->escape($salt = token(9)) . "', 
			password = '" . $this->db->escape(sha1($salt . sha1($salt . sha1($data['password'])))) . "', 
			status = '" . (int)$data['status'] . "', 
			safe = '" . (int)$data['safe'] . "',
			store_id = '" . $store_id . "',
			language_id = '" . $language_id . "',
			date_added = NOW()"

		);

		$customer_id = $this->db->getLastId();

		$address_id = $this->getCustomerAddress($customer_id, $data);
		if($address_id === false){
			$address_id = 0;
		}

		$address_id = $this->db->getLastId();		
		$this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = '" . (int)$address_id . "' WHERE customer_id = '" . (int)$customer_id . "'");
		
		return $customer_id;
	}

	private function getCustomerAddress($customer_id, $data){

		if(isset($data['country']))
		if(isset($data['country']) && isset($data['city']) && isset($data['firstname']) && isset($data['lastname'])){
			$country_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE name = '".$data['country']."' ");
			$country_id = 0;
			if(!empty($country_query->row)){
				$country_id = $country_query->row['country_id'];
			}

			if(empty($data['address1'])){
				$data['address1'] = '-- No Address --';
			}

			if(empty($data['zip'])){
				$data['zip'] = 0;
			}

			$zone_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE name = '".$data['city']."' AND country_id = '".$country_id."' ");
			$zone_id = 0;
			if(!empty($zone_query->row)){
				$zone_id = $zone_query->row['zone_id'];
			}

			$this->db->query('DELETE FROM ' . DB_PREFIX . "address WHERE customer_id = '" . (int) $customer_id . "'");
			$this->db->query("INSERT INTO " . DB_PREFIX . "address SET 
			customer_id = '" . (int)$customer_id . "', 
			firstname = '" . $this->db->escape($data['firstname']) . "', 
			lastname = '" . $this->db->escape($data['lastname']) . "', 
			address_1 = '" . $this->db->escape($data['address1']) . "', 
			city = '" . $this->db->escape($data['city']) . "', 
			postcode = '" . $this->db->escape($data['zip']) . "', 	
			country_id = '" . $country_id . "',
			zone_id = ' " . $zone_id . " '		
			");

			$address_id = $this->db->getLastId();
			return (int) $address_id;
		}

		return false;
	}

	public function editCustomer($data, $update_fields, $map_field, $existing_customer_id){
		$data['customer_group_id'] = (int) $data['customer_group_id'];
		$data['firstname'] = $this->db->escape($data['firstname']);
		$data['lastname'] = $this->db->escape($data['lastname']);
		$data['email'] = $this->db->escape($data['email']);
		$data['telephone'] = $this->db->escape($data['telephone']);
		$data['custom_field'] = $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : json_encode(array()));
		$data['newsletter'] = (int)$data['newsletter'];
		$data['status'] = (int)$data['status'];
		$data['safe'] = (int)$data['safe'];

		$data['language_id'] = (int) $this->config->get('config_language_id');
		$data['store_id'] = (int) $this->config->get('config_store_id');

		$update_fields_set = [];
		array_push($update_fields, 'customer_group_id', 'safe', 'language_id', 'store_id'); // Non-Fillable from Update Fields

		$address_id = null;
		if(in_array('address', $update_fields)){
			$address_id = $this->getCustomerAddress($existing_customer_id, $data);
			if($address_id !== false){
				array_push($update_fields_set, "address_id = '" . $address_id . "'");
			}
		}
		

		foreach($update_fields as $field){
			if(isset($data[$field])){
				array_push($update_fields_set, $field . '=' . "'" . $data[$field] . "'");
			}
		}

		$update_fields_set = implode(',', $update_fields_set);

		$this->db->query('UPDATE ' . DB_PREFIX . 'customer SET ' . $update_fields_set . ' WHERE ' . $map_field . ' = ' . "'" . $customer[$map_field] . "'");

		return ['data' => $data, 'address' => $address_id];
	} 


	public function getCustomer($customer_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row;
	}

	public function getCustomerByEmail($email) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "customer WHERE LCASE(email) = '" . $this->db->escape(utf8_strtolower($email)) . "'");

		return $query->row;
	}
	
	public function getCustomers($data = array()) {
		$sql = "SELECT *, CONCAT(c.firstname, ' ', c.lastname) AS name, cgd.name AS customer_group FROM " . DB_PREFIX . "customer c LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON (c.customer_group_id = cgd.customer_group_id)";
		
		if (!empty($data['filter_affiliate'])) {
			$sql .= " LEFT JOIN " . DB_PREFIX . "customer_affiliate ca ON (c.customer_id = ca.customer_id)";
		}		
		
		$sql .= " WHERE cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		
		$implode = array();

		if (!empty($data['filter_name'])) {
			$implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_email'])) {
			$implode[] = "c.email LIKE '" . $this->db->escape($data['filter_email']) . "%'";
		}

		if (isset($data['filter_newsletter']) && !is_null($data['filter_newsletter'])) {
			$implode[] = "c.newsletter = '" . (int)$data['filter_newsletter'] . "'";
		}

		if (!empty($data['filter_customer_group_id'])) {
			$implode[] = "c.customer_group_id = '" . (int)$data['filter_customer_group_id'] . "'";
		}

		if (!empty($data['filter_affiliate'])) {
			$implode[] = "ca.status = '" . (int)$data['filter_affiliate'] . "'";
		}
		
		if (!empty($data['filter_ip'])) {
			$implode[] = "c.customer_id IN (SELECT customer_id FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($data['filter_ip']) . "')";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$implode[] = "c.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$implode[] = "DATE(c.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'name',
			'c.email',
			'customer_group',
			'c.status',
			'c.ip',
			'c.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY name";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getAllCustomers(){
		try{
			$language_id = (int) $this->config->get('config_language_id');
			$store_id = (int) $this->config->get('config_store_id');
	
			$query_string =
				"SELECT 
					cst.customer_id, 
					cst.firstname,
					cst.lastname,
					CONCAT(cst.firstname, ' ', cst.lastname) as full_name,
					cst.email,
					cst.telephone,
					cst.fax,
					cst.password,
					cst.salt,
					cst.newsletter,
					cst.safe,
					cst.status,
					cst.date_added,
					addr.address_1,
					addr.address_2,
					addr.city,
					addr.postcode,
					cntr.name as country_name,
					zone.name as zone_name
				FROM " . DB_PREFIX . "customer cst " . 
				"LEFT JOIN " .DB_PREFIX. "address addr ON cst.address_id = addr.address_id
				LEFT JOIN " .DB_PREFIX. "country cntr ON cntr.country_id = addr.country_id
				LEFT JOIN " .DB_PREFIX. "zone zone ON zone.zone_id = addr.zone_id " .
				"WHERE language_id = '" . $language_id . "' AND store_id = '" . $store_id . "'"
			;

			$query_string = preg_replace('!\s+!', ' ', $query_string);
			$query = $this->db->query($query_string);


			$customers = [];
			foreach($query->rows as $row){
				$customer = [];
				$customer_id = $row['customer_id'];
				$customer['erpID'] = $customer_id;
				$customer['source'] = 'OpenCart';
				$customer['code1'] = $customer_id;
				$customer['username'] = $customer_id;
				$customer['password'] = $row['password'];
				$customer['salt'] = $row['salt'];
				$customer['email'] = $row['email'];
				$customer['address1'] = $row['address_1'] ?? '';
				$customer['address2'] = $row['address_2'] ?? '';
				$customer['name'] = $row['full_name'];
				$customer['area'] = $row['city'];
				$customer['city'] = $row['zone_name'] ?? '';
				$customer['zip'] = (int) $row['postcode'] ?? 0;
				$customer['country'] = $row['country_name'] ?? '';
				$customer['mobile'] = $row['telephone'] ?? '';
				$customer['landline'] = $row['fax'] ?? '';
				$customer['status'] = (int) $row['status'] ?? 0;
				$customer['is_safe'] = (int) $row['safe'] ?? 1;
				$customer['is_subscribed'] = (int) $row['newsletter'] ?? 0;
				$customer['created_at'] = $row['date_added'] ?? '2023-01-01 00:00:00';
				$customer['updated_at'] = $row['date_added'] ?? '2023-01-01 00:00:00';
				
				$customers[] = $customer;
			}
		}
		catch(Exception $ex){
			return $ex->getMessage() . $ex->getLine();
		}



		return $customers;
	}

	public function getAddress($address_id) {
		$address_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "address WHERE address_id = '" . (int)$address_id . "'");

		if ($address_query->num_rows) {
			$country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$address_query->row['country_id'] . "'");

			if ($country_query->num_rows) {
				$country = $country_query->row['name'];
				$iso_code_2 = $country_query->row['iso_code_2'];
				$iso_code_3 = $country_query->row['iso_code_3'];
				$address_format = $country_query->row['address_format'];
			} else {
				$country = '';
				$iso_code_2 = '';
				$iso_code_3 = '';
				$address_format = '';
			}

			$zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$address_query->row['zone_id'] . "'");

			if ($zone_query->num_rows) {
				$zone = $zone_query->row['name'];
				$zone_code = $zone_query->row['code'];
			} else {
				$zone = '';
				$zone_code = '';
			}

			return array(
				'address_id'     => $address_query->row['address_id'],
				'customer_id'    => $address_query->row['customer_id'],
				'firstname'      => $address_query->row['firstname'],
				'lastname'       => $address_query->row['lastname'],
				'company'        => $address_query->row['company'],
				'address_1'      => $address_query->row['address_1'],
				'address_2'      => $address_query->row['address_2'],
				'postcode'       => $address_query->row['postcode'],
				'city'           => $address_query->row['city'],
				'zone_id'        => $address_query->row['zone_id'],
				'zone'           => $zone,
				'zone_code'      => $zone_code,
				'country_id'     => $address_query->row['country_id'],
				'country'        => $country,
				'iso_code_2'     => $iso_code_2,
				'iso_code_3'     => $iso_code_3,
				'address_format' => $address_format,
				'custom_field'   => json_decode($address_query->row['custom_field'], true)
			);
		}
	}

	public function getAddresses($customer_id) {
		$address_data = array();

		$query = $this->db->query("SELECT address_id FROM " . DB_PREFIX . "address WHERE customer_id = '" . (int)$customer_id . "'");

		foreach ($query->rows as $result) {
			$address_info = $this->getAddress($result['address_id']);

			if ($address_info) {
				$address_data[$result['address_id']] = $address_info;
			}
		}

		return $address_data;
	}

	public function getTotalCustomers($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer c";

		$implode = array();

		if (!empty($data['filter_name'])) {
			$implode[] = "CONCAT(firstname, ' ', lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_email'])) {
			$implode[] = "email LIKE '" . $this->db->escape($data['filter_email']) . "%'";
		}

		if (isset($data['filter_newsletter']) && !is_null($data['filter_newsletter'])) {
			$implode[] = "newsletter = '" . (int)$data['filter_newsletter'] . "'";
		}

		if (!empty($data['filter_customer_group_id'])) {
			$implode[] = "customer_group_id = '" . (int)$data['filter_customer_group_id'] . "'";
		}

		if (!empty($data['filter_ip'])) {
			$implode[] = "customer_id IN (SELECT customer_id FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($data['filter_ip']) . "')";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$implode[] = "status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$implode[] = "DATE(date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}
        
	
	public function getAffiliate($customer_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_affiliate WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row;
	}
	
	public function getAffiliates($data = array()) {
		$sql = "SELECT DISTINCT *, CONCAT(c.firstname, ' ', c.lastname) AS name FROM " . DB_PREFIX . "customer_affiliate ca LEFT JOIN " . DB_PREFIX . "customer c ON (ca.customer_id = c.customer_id)";
		
		$implode = array();

		if (!empty($data['filter_name'])) {
			$implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}		
		
		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}
		
		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}
						
		$query = $this->db->query($sql . "ORDER BY name");

		return $query->rows;
	}
	
	public function getTotalAffiliates($data = array()) {
		$sql = "SELECT DISTINCT COUNT(*) AS total FROM " . DB_PREFIX . "customer_affiliate ca LEFT JOIN " . DB_PREFIX . "customer c ON (ca.customer_id = c.customer_id)";
		
		$implode = array();

		if (!empty($data['filter_name'])) {
			$implode[] = "CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}		
		
		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}
		
		return $query->row['total'];
	}

	public function getTotalAddressesByCustomerId($customer_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "address WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row['total'];
	}

	public function addHistory($customer_id, $comment) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "customer_history SET customer_id = '" . (int)$customer_id . "', comment = '" . $this->db->escape(strip_tags($comment)) . "', date_added = NOW()");
	}

	public function getHistories($customer_id, $start = 0, $limit = 10) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$query = $this->db->query("SELECT comment, date_added FROM " . DB_PREFIX . "customer_history WHERE customer_id = '" . (int)$customer_id . "' ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function getTotalHistories($customer_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_history WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row['total'];
	}

	public function addTransaction($customer_id, $description = '', $amount = '', $order_id = 0) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "customer_transaction SET customer_id = '" . (int)$customer_id . "', order_id = '" . (int)$order_id . "', description = '" . $this->db->escape($description) . "', amount = '" . (float)$amount . "', date_added = NOW()");
	}

	public function deleteTransactionByOrderId($order_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "customer_transaction WHERE order_id = '" . (int)$order_id . "'");
	}

	public function getTransactions($customer_id, $start = 0, $limit = 10) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$customer_id . "' ORDER BY date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function getTotalTransactions($customer_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total  FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row['total'];
	}

	public function getTransactionTotal($customer_id) {
		$query = $this->db->query("SELECT SUM(amount) AS total FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . (int)$customer_id . "'");

		return $query->row['total'];
	}

	public function getTotalTransactionsByOrderId($order_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_transaction WHERE order_id = '" . (int)$order_id . "'");

		return $query->row['total'];
	}


	public function getTotalCustomersByIp($ip) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_ip WHERE ip = '" . $this->db->escape($ip) . "'");

		return $query->row['total'];
	}

	public function getTotalLoginAttempts($email) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_login` WHERE `email` = '" . $this->db->escape($email) . "'");

		return $query->row;
	}

	public function deleteLoginAttempts($email) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_login` WHERE `email` = '" . $this->db->escape($email) . "'");
	}
}
