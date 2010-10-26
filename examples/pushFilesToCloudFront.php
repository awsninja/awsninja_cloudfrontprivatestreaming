#!/usr/bin/php -q
<?php

/**
 * CloudFrontPrivateStreaming
 * 
 * Provide a framework for managing Images on Amazon Cloudfront.  Supports
 * the automatic versioning, redimensioning and URL generation for images
 * for use in web applications.
 * 
 * Requires MagickWand for ImageMagick: http://www.magickwand.org/
 * 
 * @author Jay Muntz
 * 
 * Copyright 2010 Jay Muntz (http://www.awsninja.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * “Software”), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 *
 */

define('NINJA_BASEPATH', dirname(__FILE__) . '/../../');

putenv('HOME=' . NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/');//env path for AWS PHP SDK
require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/config.php';
require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/sdk/sdk.class.php';

$s3 = new AmazonS3();

$viddir = opendir(NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/video/');

while(false !== ($file = readdir($viddir)))
{
	if ($file != "." && $file != "..")
	{
		echo("Put $file on S3\n");
	  $cbr = $s3->create_object(NINJA_STREAMING_BUCKET, $file, array(
			'fileUpload'=>NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/video/' . $file,
			'acl'=>AmazonS3::ACL_OWNER_FULL_CONTROL
		));

	
		$acl = array(
			array(
				'id'=>AWS_CANONICAL_ID,
				'permission'=>AmazonS3::GRANT_FULL_CONTROL
			),
			array(
				'id'=>NINJA_STREAMING_CANONICALID,
				'permission'=>AmazonS3::GRANT_READ
			)
		);
	
		echo("Update the ACL for $file on S3.\n\n");
		$sas = $s3->set_object_acl(NINJA_STREAMING_BUCKET, $file, $acl);
		if (!$sas->isOK())
		{
			echo("ERROR\n");
			print_r($sas->header);
			echo("{$sas->body}\n");
			echo("{$sas->status}\n");
			exit;
		}
	}
}

echo("All Done!\n");


?>
