<?php

class ModelCatalogSkSettings extends Model
{
    public function getCurrencies()
    {
        $query = $this->db->query('SELECT currency_id, code FROM ' . DB_PREFIX . 'currency');

        return $query->rows;
    }

    public function getLanguages()
    {
        $query = $this->db->query('SELECT language_id, name FROM ' . DB_PREFIX . 'language');

        return $query->rows;
    }

    public function getTaxClasses()
    {
        $query = $this->db->query('SELECT tax_class_id, title FROM ' . DB_PREFIX . 'tax_class');

        return $query->rows;
    }

    public function getWeightClasses()
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'weight_class wc INNER JOIN ' . DB_PREFIX . 'weight_class_description wcd on wcd.weight_class_id = wc.weight_class_id');

        return $query->rows;
    }

    public function getLengthClasses()
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'length_class lc INNER JOIN ' . DB_PREFIX . 'length_class_description lcd on lcd.length_class_id = lc.length_class_id');

        return $query->rows;
    }

    public function getStockStatuses()
    {
        $query = $this->db->query('SELECT stock_status_id, CONCAT( '.DB_PREFIX.'stock_status.name, " (", '.DB_PREFIX.'language.name, ")" ) as name FROM ' . DB_PREFIX . 'stock_status  LEFT JOIN ' . DB_PREFIX . 'language ON ' . DB_PREFIX . 'language.language_id = '.DB_PREFIX.'stock_status.language_id WHERE '.DB_PREFIX.'language.name IS NOT NULL');

        return $query->rows;
    }

    public function getCustomerGroups(){
        $query = $this->db->query(
            'SELECT customer_group_id, CONCAT( grp.name, " (", lang.name, ")") AS name 
            FROM ' . DB_PREFIX . 'customer_group_description grp 
            JOIN ' . DB_PREFIX . 'language lang ON grp.language_id = lang.language_id');

        return $query->rows;
    }

    public function getAllSettings()
    {
        return [
            'customergroups' => $this->getCustomerGroups(),
            'currencies' => $this->getCurrencies(),
            'taxclasses' => $this->getTaxClasses(),
            'lengthclasses' => $this->getLengthClasses(),
            'stockstatuses' => $this->getStockStatuses(),
            'weightclasses' => $this->getWeightClasses(),
            'languages' => $this->getLanguages(),
        ];
    }


}
