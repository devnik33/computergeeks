<?php

class ControllerApiSkManufacturer extends Controller
{
    public function list()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));

            return false;
        }

        $this->response->addHeader('Content-Type: application/json');

        $this->load->model('catalog/sk_manufacturer');

        $results = $this->model_catalog_sk_manufacturer->getManufacturers();

        $this->response->setOutput(json_encode($results));
    }
    //Same as list for a test
    public function index()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));

            return false;
        }

        $this->response->addHeader('Content-Type: application/json');

        $this->load->model('catalog/sk_manufacturer');

        $results = $this->model_catalog_sk_manufacturer->getManufacturers();

        $this->response->setOutput(json_encode($results));
    }



    public function add()

    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');

            $this->response->setOutput(json_encode($json));
        } else {
            $this->load->model('catalog/sk_manufacturer');
            $data = $this->request->post;

            //first we check if the manufacturer already exist

            $manufacturerExists = $this->model_catalog_sk_manufacturer->getManufacturerByName($data['manufacturer_description_name']);
            if (count($manufacturerExists) > 0) {
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(['success' => false, 'message' => 'Manufacturer already exists', 'id' => $manufacturerExists['manufacturer_id']]));

                return '';
            }

            $postItems = [

                'name' => $data['manufacturer_description_name'],
                'manufacturer_store' => ['0'],
                'image' => '',
                'sort_order' => '0',

            ];

            $id = $this->model_catalog_sk_manufacturer->addManufacturer($postItems);

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['success' => true, 'message' => 'Manufacturer was successfully added', 'id' => $id]));
        }
    }

    public function add2()
    {
        if (!isset($this->session->data['api_id'])) {
            $json['error'] = 'Unauthorized';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        } else {
            $this->load->model('catalog/sk_manufacturer');
            $datas = $this->request->post;
            $ids = [];

            foreach ($datas['manufacturers'] as $data)
            {
                $manufacturerExists = $this->model_catalog_sk_manufacturer->getManufacturerByName(html_entity_decode(ucfirst($data['name'])));
                if(count($manufacturerExists) == 0)
                {
                    $postItems = [

                        'name' => html_entity_decode(ucfirst($data['name'])),
                        'manufacturer_store' => ['0'],
                        'image' => '',
                        'sort_order' => '0',
        
                    ];
                    $id = [];
                    $id['target_id'] = $this->model_catalog_sk_manufacturer->addManufacturer($postItems);
                    $id['source_id'] = $data['manufacturer_id'];

                    array_push($ids,$id);
                }
                else{
                    $id = [];
                    $id['target_id'] = $manufacturerExists['manufacturer_id'];
                    $id['source_id'] = $data['manufacturer_id'];

                    array_push($ids,$id);
                }
            }
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(['success' => true, 'message' => 'Manufacturers was successfully added', 'ids' => $ids]));
            }
        } 
    
}