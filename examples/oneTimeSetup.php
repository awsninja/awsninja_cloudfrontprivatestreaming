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

putenv('HOME=' . NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/' );//env path for AWS PHP SDK
//require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/config.php';
require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/sdk/sdk.class.php';
require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/ninja.cloudfront.class.php';  //Get the customized Ninja version of the CloudFront service.

//Instanciate the services
$cfs = new AmazonCloudFrontNinja();
$s3 = new AmazonS3();


$timeStamp = (string)time();  //create a timestamp string for unique identifiers

echo("Please enter a name for your S3 bucket for CloudFront Streaming:\n");
$bucket = trim(fgets(STDIN));

echo("Creating a bucket named {$bucket}\n");
$cbr = $s3->create_bucket($bucket, AmazonS3::REGION_US_E1);

//Make sure the bucket create operation was successful.
if (!$cbr->isOk())
{
	echo("ERROR - could not create bucket $bucket\n");
	print_r($cbr->header);
	echo("{$cbr->body}\n");
	echo("{$cbr->status}\n");
	exit;
}

//Bucket creation take a moment. Poll until it's finished.
echo("Wait a short moment for Create Bucket operation to finish.\n\n");
$exists = $s3->if_bucket_exists($bucket);
while (!$exists)
{
	sleep(1);
	$exists = $s3->if_bucket_exists($bucket);
}
sleep(1);
echo("Done.\n");
echo("Now creating an Origin Access Identity. . .\n");

//You need a unqiue caller reference. We'll create one.
$oaiCallerRef = 'ninja-' . date('Y-m-d H:i:s', $timeStamp);
$cor = $cfs->create_oai("ninja-{$oaiCallerRef}");
if (!$cor->isOK()) //make sure the command completed successful
{
	echo("ERROR creating OriginAccessIdentity\n\n");
	print_r($cor->header);
	echo("{$cor->body}\n");
	echo("{$cor->status}\n");
	exit;
}
sleep(1);

$oaiId = (string)$cor->body->Id;  //capture the Origin Access Identity id
$oaiCannId = (string)$cor->body->S3CanonicalUserId;  //capture the Origin Access Identity Cannonical Id
echo("OAI Created.\n");
sleep(1);
echo("Now we're creating a Streaming CloudFront Distribution for the bucket and OID we just created . . . \n\n");
$cdr = $cfs->create_distribution($bucket, $bucket, array('OriginAccessIdentity'=>$oaiId, 'Streaming'=>true, 'TrustedSigners'=>'Self' ));
//make sure the operation was okay.
if (!$cdr->isOK())
{
	echo("ERROR\n");
	print_r($cdr->header);
	echo("{$cdr->body}\n");
	echo("{$cdr->status}\n");
	exit;
}


$domainName = (string)$cdr->body->DomainName; //capture the domain name
$distributionId = (string)$cdr->body->Id; //capture the domain name


echo("Distribution creation operation was successful.\n\n");

echo("Add these lines to config.php:\n\n");
echo <<<EOF
define('NINJA_STREAMING_BUCKET', '{$bucket}');
define('NINJA_STREAMING_OAIID', '{$oaiId}');
define('NINJA_STREAMING_CANNONICALID', '{$oaiCannId}');
define('NINJA_STREAMING_DOMAIN_NAME', '{$domainName}');


EOF;


echo("Now we poll the Distribution until it enters the \"Deployed\" state.  You can take copy the PHP variables above to your other scripts.  Execution of this script will end when your Distribution is fully deployed.\n\n");



$start = time();

$status = 'InProgress';  //Initialize $status as "InProgress"
while($status == 'InProgress')
{
	$now = time();
	$wait = $now-$start;
	$min = floor($wait/60);
	$sec = $wait%60;
	echo("Checking the status of your Distribution. . .\n");
	$distObj = $cfs->get_distribution_info($distributionId , array('Streaming'=>true));
	$status = (string)$distObj->body->Status;
	echo("The status is $status.  This process takes up to 15 minutes.  You've been waiting $min minutes, $sec seconds.\n");
	sleep(5);
}


echo("ALL DONE.  Your streaming distribution is fully operational.\n\nATTENTION: THESE LINES NEED TO BE PUT IN YOUR config.php FILE:\n\n");
echo <<<EOF
define('NINJA_STREAMING_BUCKET', '{$bucket}');
define('NINJA_STREAMING_OAIID', '{$oaiId}');
define('NINJA_STREAMING_CANNONICALID', '{$oaiCannId}');
define('NINJA_STREAMING_DOMAIN_NAME', '{$domainName}');

EOF;




?>
