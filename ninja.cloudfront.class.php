<?php

	/*
		AmazonCloudFrontNinja extends the AmazonCloudFront class that is part of the AWS PHP SDK
		The original get_private_object_url() method returns an complete URL to a resource.  When streaming
		resources the JW Player takes two resources: "streamer" and "file".  The functionality of get_private_object_url()
		is broken out into two methods which return those two parts separatly.
	*/
	
	class AmazonCloudFrontNinja extends AmazonCloudFront
	{
		public function __construct($key = null, $secret_key = null)
		{
			return parent::__construct($key, $secret_key);
		}
	
		public function get_private_object_path($filename, $expires, $opt = null)
		{
			$expiration_key = 'Expires';
			$conjunction = (strpos($filename, '?') === false ? '?' : '&');
			// Generate default policy
			$raw_policy = array(
				'Statement' => array(
					array(
						'Resource' => $filename,
						'Condition' => array(
							'DateLessThan' => array(
								'AWS:EpochTime' => $expires
							)
						)
					)
				)
			);

			// Become Available
			if (isset($opt['BecomeAvailable']))
			{
				// Switch to 'Policy' instead
				$expiration_key = 'Policy';

				// Update the policy
				$raw_policy['Statement'][0]['Condition']['DateGreaterThan'] = array(
					'AWS:EpochTime' => strtotime($opt['BecomeAvailable'])
				);
			}

			// IP Address
			if (isset($opt['IPAddress']))
			{
				// Switch to 'Policy' instead
				$expiration_key = 'Policy';

				// Update the policy
				$raw_policy['Statement'][0]['Condition']['IpAddress'] = array(
					'AWS:SourceIp' => $opt['IPAddress']
				);
			}

			// Munge the policy
			$json_policy = str_replace('\/', '/', json_encode($raw_policy));
			$json_policy = $this->util->decode_uhex($json_policy);
			$encoded_policy = strtr(base64_encode($json_policy), '+=/', '-_~');

			// Generate the signature
			$signature = null;
			$res = openssl_sign($json_policy, $signature, $this->private_key);
			$signature = strtr(base64_encode($signature), '+=/', '-_~');
			return  str_replace(array('%3F', '%3D', '%26', '%2F'), array('?', '=', '&', '/'), rawurlencode($filename))
			. $conjunction
			. ($expiration_key === 'Expires' ? ($expiration_key . '=' . $expires) : ($expiration_key . '=' . $encoded_policy))
			. '&Key-Pair-Id=' . $this->key_pair_id
			. '&Signature=' . $signature;
		}
	


	/**
	 * Method: get_private_object_url()
	 * 	Generates a time-limited and/or query signed request for a private file with additional optional
	 * 	restrictions.  NOTE: This implementation overrides the origial get_private_object_url() from AmazonCloudFront
	 *	in the AWS PHP SDK.
	 *
	 * Access:
	 * 	public
	 *
	 * Parameters:
	 * 	$distribution_hostname - _string_ (Required) The hostname of the distribution. Obtained from <create_distribution()> or <get_distribution_info()>.
	 *	$filename - _string_ (Required) The file name of the object. Query parameters can be included. You can use multicharacter match wild cards () or a single-character match wild card (?) anywhere in the string.
	 * 	$expires - _integer_|_string_ (Required) The expiration time expressed either as a number of seconds since UNIX Epoch, or any string that `strtotime()` can understand.
	 * 	$opt - _array_ (Optional) An associative array of parameters that can have the keys listed in the following section.
	 *
	 * Keys for the $opt parameter:
	 *	BecomeAvailable - _integer_|_string_ (Optional) The time when the private URL becomes active. Can be expressed either as a number of seconds since UNIX Epoch, or any string that `strtotime()` can understand.
	 *	IPAddress - _string_ (Optional) A single IP address to restrict the access to.
	 * 	Secure - _boolean_ (Optional) Whether or not to use HTTPS as the protocol scheme. A value of `true` uses `https`. A value of `false` uses `http`. Defaults to `false`.
	 *
	 * Returns:
	 * 	_string_ The file URL with authentication parameters.
	 *
	 * See Also:
	 * 	[Serving Private Content](http://docs.amazonwebservices.com/AmazonCloudFront/latest/DeveloperGuide/PrivateContent.html)
	 */
	public function get_private_object_url($distribution_hostname, $filename, $expires, $opt = null)
	{
		if (!$this->key_pair_id || !$this->private_key)
		{
			throw new CloudFront_Exception('You must set both a Amazon CloudFront keypair ID and an RSA private key for that keypair before using ' . __FUNCTION__ . '()');
		}
		if (!function_exists('openssl_sign'))
		{
			throw new CloudFront_Exception(__FUNCTION__ . '() uses functions from the OpenSSL PHP Extension <http://php.net/openssl>, which is not installed in this PHP installation');
		}

		if (!$opt) $opt = array();

		$resource = '';
//		$expiration_key = 'Expires';
		$expires = strtotime($expires);
	

		// Determine the protocol scheme
		switch (substr($distribution_hostname, 0, 1) === 's')
		{
			// Streaming
			case 's':
				$scheme = 'rtmp';
				$resource = str_replace(array('%3F', '%3D', '%26', '%2F'), array('?', '=', '&', '/'), rawurlencode($filename));
				break;

			// Default
			case 'd':
			default:
				$scheme = 'http';
				$scheme .= (isset($opt['Secure']) && $opt['Secure'] === true ? 's' : '');
				$resource = $scheme . '://' . $distribution_hostname . '/' . str_replace(array('%3F', '%3D', '%26', '%2F'), array('?', '=', '&', '/'), rawurlencode($filename));
				break;
		}
//This functionality is moved to the get_private_object_path() method
//		// Generate default policy
//		$raw_policy = array(
//			'Statement' => array(
//				array(
//					'Resource' => $resource,
//					'Condition' => array(
//						'DateLessThan' => array(
//							'AWS:EpochTime' => $expires
//						)
//					)
//				)
//			)
//		);
//
//		// Become Available
//		if (isset($opt['BecomeAvailable']))
//		{
//			// Switch to 'Policy' instead
//			$expiration_key = 'Policy';
//
//			// Update the policy
//			$raw_policy['Statement'][0]['Condition']['DateGreaterThan'] = array(
//				'AWS:EpochTime' => strtotime($opt['BecomeAvailable'])
//			);
//		}
//
//		// IP Address
//		if (isset($opt['IPAddress']))
//		{
//			// Switch to 'Policy' instead
//			$expiration_key = 'Policy';
//
//			// Update the policy
//			$raw_policy['Statement'][0]['Condition']['IpAddress'] = array(
//				'AWS:SourceIp' => $opt['IPAddress']
//			);
//		}
//
//		// Munge the policy
//		$json_policy = str_replace('\/', '/', json_encode($raw_policy));
//		$json_policy = $this->util->decode_uhex($json_policy);
//		$encoded_policy = strtr(base64_encode($json_policy), '+=/', '-_~');
//
//		// Generate the signature
//		openssl_sign($json_policy, $signature, $this->private_key);
//		$signature = strtr(base64_encode($signature), '+=/', '-_~');

			$path = $this->get_private_object_path($resource, $expires, $opt);
			return $scheme . '://' . $distribution_hostname . '/' . $path;
		}
	}
?>

