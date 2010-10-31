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

//env path for AWS PHP SDK
putenv('HOME=' . NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/');
require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/sdk/sdk.class.php';
require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/ninja.cloudfront.class.php';  //Get the customized ninja version of the CloudFront service.

//Intanciate the AmazonCloudFrontNinja service
$cfs = new AmazonCloudFrontNinja();

//Get the list of streaming distributions on our account
$dists = $cfs->list_distributions(array('Streaming'=>true));

//$buckets holds the list of bucket names that are associated with Distributions that get
//deleted so we can echo them at the end rather than automatically delete your content.
$buckets = array();

//Loop through the results
foreach($dists->body->StreamingDistributionSummary as $dist)
{
	//You can only delete distributions that are are disabled.  Unfortunately, the process of
	//disabling a distribution and then deleting it has many steps and is time consuming.
	//Here, the entire process is automated and documented.  Reading the comments and code 
	//below may give you a better understanding of the CloudFront Streaming architecture.

	$distributionId = (string)$dist->Id;  //capture the distribution id
	
	//WHY DOES THIS CODE KEEP SPECIFYING Streaming=true?
	//The reason is that even though we have the Distribution Id, we also need to overtly
	//specify that it's a streaming distribution.  If that seems redundant to you, you're
	//right!  This is made necessary because of the way AWS designed the CloudFront API.
	//(I'm noting this because it's a tricky detail when working with distributions.)
	
	//Use the get_distribution_info() to get the etag, which the CloudFront API requires us 
	//to send when changing the config.
	$distObj = $cfs->get_distribution_info($distributionId , array('Streaming'=>true));
	$etag = (string)$distObj->header['etag'];  //capture the etag
	
	//Retrieve the Distribution config, so we can modify it.  Of course, Streaming=true.
	$distConfig = $cfs->get_distribution_config($distributionId, array('Streaming'=>true));
	$origin = (string)$distConfig->body->Origin;
	$bucket = substr($origin, 0, strlen($origin)-strlen('.s3.amazonaws.com'));

	$buckets[] = $bucket; //record the bucket name in $buckets

	$configXML = (string)$distConfig->body->asXml();  //get the config as an xml string
	
	//Use the update_config_xml() method to set "Enabled=false".  Again, "Streaming=true". 
	$newConfigXml = $cfs->update_config_xml($configXML, array('Streaming'=>true, 'Enabled'=>false));

	//Now use the set_distribution_config() to change the config.  Once again, "Streaming=true".
	$res = $cfs->set_distribution_config($distributionId,  $newConfigXml , $etag, array('Streaming'=>true));

	//Now we're going to poll the Distribution until it tells us it is disabled, just to make sure it worked.
	$enabled = true; //Initialize $enabled as true
	while($enabled)
	{
		echo("Check if disabled\n");
		$distConfig = $cfs->get_distribution_config($distributionId, array('Streaming'=>true));
		$enabledStr = (string)$distConfig->body->Enabled[0];
		
		if($enabledStr == 'false')  //YUK!  But for some reason I couldn't cast the response to a bool.
		{
			echo("Ok, it's disabled\n");
			$enabled = false;  //This exits the while loop.
		}
		else
		{
			echo("Sleep for one second. . .\n");
			sleep(1);
		}
	}

	//Now we will poll the status of the distribution.  When we change the config of a distribution, its 
	//Status changes to "InProgress" - meaning that the change is In Progress.  Once the change is complete
	//the status will be "Deployed".  NOTE: Even though we are setting Enabled=false, the distribution is 
	//disabled when it's status becomes "Deployed".  "Deployed" means that your config changes are deployed,
	//it doesn't mean that your Distribution is deployed.  This confused me at first, but you're probably
	//smarter than me.
	echo("Now waiting for the Status of the Distribution to change to \"Deployed\".  This takes several minutes.\n");

	$status = 'InProgress';  //Initialize $status as "InProgress"
	while($status == 'InProgress')
	{
		echo("Check status:\n");
		$distObj = $cfs->get_distribution_info($distributionId , array('Streaming'=>true));
		$status = (string)$distObj->body->Status;
		echo("Status is $status, Sleep for five seconds\n");
		sleep(5);
	}
	
	echo("The distribution is now disabled, so we can delete it.\n");
	
	//The delete_distribution() method requires a valid etag.  Since we changed the Distribution config,
	//the etag we retrieved above is no longer valid.  So, get the new etag:
	$distObj = $cfs->get_distribution_info($distributionId , array('Streaming'=>true));
	$etag = (string)$distObj->header['etag'];

	//Call the delete_distribution() method to finally delete the distribution.
	$res = $cfs->delete_distribution($distributionId, $etag, array('Streaming'=>true));
	echo("DELETED\n");
}

//Now we'll go to work deleting Origin Access Identities
$oais = $cfs->get_oai_list();
$oaict = count($oais);

echo("Removing $oaict Origin Access Identities\n");

for($i=0; $i<$oaict; $i++)
{
	$oaiId = $oais[$i];
	$res = $cfs->get_oai($oaiId);
	$etag = (string)$res->header['etag'];
	$res = $cfs->delete_oai($oaiId, $etag);
	echo("OAI $oaiId removed.\n");
}

echo("All OAIs are removed.  Below are the buckets associated with the distributions that were just removed:");

$bucCt = count($buckets);
for($i=0;$i<$bucCt;$i++)
{
	echo($buckets[$i] . "\n");
}

echo("You can use your favorite S3 client (S3Fox, CloudBerry, or whatever) to delete these buckets.\n");
echo("All done!\n");

?>
