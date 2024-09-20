<?php

class ControllerApiSkcategory extends Controller
{

	public function test(){

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['tolis' => 'is here']));

	}

}