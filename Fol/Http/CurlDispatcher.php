<?php
/**
 * Fol\Http\CurlDispatcher
 *
 * Class to send http requests using curl
 */
namespace Fol\Http;

class CurlDispatcher
{
    protected $options = [];


    /**
     * Constructor
     * 
     * @param array $options
     */
    public function __construct(array $options = null)
    {
        if ($options) {
            $this->setOptions($options);
        }
    }


    /**
     * Set custom curl options
     * 
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }


    /**
     * Executes a request and returns a response
     * 
     * @param Request  $request
     * 
     * @return Response
     */
    public function getResponse(Request $request, Response $response = null)
    {
        if (!$response) {
            $response = new Response;
            $response->setBody('php://temp', true);
        }

        $connection = $this->prepare($request, $response);
        $return = curl_exec($connection);

        if (!$response->isStream()) {
            $response->setBody($return);
        }

        $info = curl_getinfo($connection);
        curl_close($connection);

        $response->setStatus($info['http_code']);

        return $response;
    }



    /**
     * Prepares the curl connection before execute
     * 
     * @param Request  $request
     * @param Response $response
     * 
     * @return resource The cURL handle
     */
	protected function prepare(Request $request, Response $response)
	{
		$connection = curl_init();

		curl_setopt_array($connection, [
            CURLOPT_URL => $request->getUrl(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIE => $request->cookies->getAsString(null, ''),
            CURLOPT_SAFE_UPLOAD => true
        ]);

        curl_setopt($connection, CURLOPT_HTTPHEADER, $request->headers->getAsString());

        if ($request->getMethod() === 'POST') {
        	curl_setopt($connection, CURLOPT_POST, true);
        } else if ($request->getMethod() === 'PUT') {
        	curl_setopt($connection, CURLOPT_PUT, true);
        } else if ($request->getMethod() !== 'GET') {
        	curl_setopt($connection, CURLOPT_CUSTOMREQUEST, $request->getMethod());
        }

        curl_setopt($connection, CURLOPT_HEADERFUNCTION, function ($connection, $string) use ($response) {
        	if (strpos($string, ':')) {
                $response->headers->setFromString($string, false);

        		if (strpos($string, 'Set-Cookie') === 0) {
                    $response->cookies->setFromString($string);
                }
        	}

        	return strlen($string);
        });

        if ($response->isStream()) {
            curl_setopt($connection, CURLOPT_WRITEFUNCTION, function ($connection, $string) use ($response) {
                return $response->write($string, strlen($string));
            });
        }

        $data = $request->data->get();

		foreach ($request->files->get() as $name => $file) {
			$data[$name] = new CURLFile($file, '', $name);
		}

    	if ($data) {
    		curl_setopt($connection, CURLOPT_POSTFIELDS, $data);
    	} else if (($body = $request->getBody())) {
            if (is_string($body)) {
                curl_setopt($connection, CURLOPT_POSTFIELDS, $body);
            } else {
                curl_setopt($connection, CURLOPT_INFILE, $body);
                curl_setopt($connection, CURLOPT_INFILESIZE, 1024);

                curl_setopt($connection, CURLOPT_READFUNCTION, function ($connection, $stream, $length) {
                    return fread($stream, $length);
                });
            }
        }

        if ($this->options) {
            curl_setopt_array($connection, $this->options);
        }

    	return $connection;
	}
}