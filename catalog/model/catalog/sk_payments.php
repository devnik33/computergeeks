<?php

class ModelCatalogSkPayments extends Model
{
    public function getPaymentExtensions()
    {
        $paymentExtensions = $this->db->query("
			SELECT 
			*
			FROM `" . DB_PREFIX . "extension` o 
			WHERE 
                `type`='payment'		
		
			");
		
        return $paymentExtensions->rows;
    }


    /**
     * orderStatuses - returns all order statuses from db
     * @return json array
     */

    public function getOrderStatuses()
    {
        $orderStatuses = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_status`");		
        return $orderStatuses->rows;
    }
}