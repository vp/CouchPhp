<?php

namespace CouchPhp\Clients;

use CouchPhp\Object,
	CouchPhp\IClient,
	CouchPhp\Request,
	CouchPhp\Response,
	CouchPhp\IProfiler,
	CouchPhp\RequestException;



/**
 * CouchDB client based on CURL functions.
 */
class CurlClient extends Object implements IClient
{
	/**
	 * @param  Request
	 * @param  IProfiler
	 * @return Response
	 */
	public function makeRequest(Request $request, IProfiler $profiler = NULL)
	{
		if ($profiler !== NULL) {
			$profiler->logRequest($request);
		}

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($curl, CURLOPT_URL, $request->getUri());

		$headers = array();
		foreach ($request->getHeaders() as $k => $v) {
			$headers[] = "$k: $v";
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		if ($request->getMethod() === 'HEAD') {
			curl_setopt($curl, CURLOPT_NOBODY, TRUE);
		} else {
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request->getMethod());
		}

		if ($request->getFile() !== NULL) {
			curl_setopt($curl, CURLOPT_UPLOAD, TRUE);
			curl_setopt($curl, CURLOPT_INFILESIZE, filesize($request->getFile()));
			curl_setopt($curl, CURLOPT_INFILE, fopen($request->getFile(), 'r'));
		} elseif (in_array($request->getMethod(), array('POST', 'PUT'), TRUE)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $request->getPostData());
		}

		$time = microtime(TRUE);
		$body = curl_exec($curl);
		$time = microtime(TRUE) - $time;

		if ($code = curl_errno($curl)) {
			throw new RequestException(curl_error($curl), $code, $request);
		}

		$hLen = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$response = new Response(
			curl_getinfo($curl, CURLINFO_HTTP_CODE),
			(string) substr($body, 0, $hLen),
			(string) substr($body, $hLen),
			$time
		);

		if ($profiler !== NULL) {
			$profiler->logResponse($response);
		}
		return $response;
	}
}
