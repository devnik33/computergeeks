<?php

class ControllerApiSkProduct extends Controller
{
    public function dee44edf4rfFGFsderd5t55ytbdfd837jdfRF()
    {

        if (!isset($this->session->data['api_id'])) {
			$json['error'] = ['message' => 'Unauthorised'];
			$this->response->addHeader('Content-Type: application/json');

			$this->response->setOutput(json_encode($json));
			return true;
		}

        // remove file if exists
        // download
        //file_put_contents("Tmpfile.zip", fopen("http://someurl/file.zip", 'r'));

        
    }

}
