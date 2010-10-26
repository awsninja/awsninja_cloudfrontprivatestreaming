<?php

	define('NINJA_BASEPATH', dirname(__FILE__) . '/../../');

	//env path for AWS PHP SDK
	putenv('HOME=' . NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/');
	require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/config.php';
	require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/sdk/sdk.class.php';
	require_once NINJA_BASEPATH . 'awsninja_cloudfrontprivatestreaming/ninja.cloudfront.class.php';
	
	$cfs = new AmazonCloudFrontNinja();
	$s3 = new AmazonS3();


	//DEPLOYMENT CHECK - makes sure that your distribution is deployed before attempting to stream.
	//Comment this out after you successfully get the streaming working
	$distList = $cfs->get_streaming_distribution_list();
	$distListCt = count($distList);
	
	for($i=0;$i<$distListCt; $i++)
	{
		$distId = (string)$distList[$i];
		$distInfo = $cfs->get_distribution_info($distId, array('Streaming'=>true));
		$testDomainName = (string)$distInfo->body->DomainName;
		$status = (string)$distInfo->body->Status;
		$enabled = (bool)$distInfo->body->StreamingDistributionConfig->Enabled;
		if (NINJA_STREAMING_DOMAIN_NAME == $testDomainName)
		{
			if($status = 'Deployed' && $enabled)
			{
				echo("Congratuations!  You distribution is now deployed.  You should remove the Distrubtion State Check found at lines ~21-45 of index.php.");
			}
			else
			{
				echo("Not Ready yet.  Your Distribution status is $status.  It may take up to 15 minutes.  Be patient.\n");
				exit;
			}
		}		
	}
	//END - DEPLOYMENT CHECK
	
	

	
	
	
	function getEmbed($item, $distributionDomain)
	{
		$expiration = strtotime('+4320 minutes');  //three days

		$opt = array(
		//	'BecomeAvailable'=>strtotime('+10 minutes'),  //starts working in 10 minutes
			'IPAddress'=> $_SERVER['REMOTE_ADDR']  //limit to the current IP address (prevents the user from embedding the flash player to serve the content to other users)
		);

		$cfsn = new AmazonCloudFrontNinja();
		$urlcloud = $cfsn->get_private_object_path($item, $expiration, $opt);
		
		return <<<EOF
<div id="flv{$item}" style="margin-left: auto; margin-right: auto; text-align: center; margin-bottom: 20px;"><a href="http://www.macromedia.com/go/getflashplayer">Get the Flash Player</a> to see this player.</div>
<script type='text/javascript'>
video=encodeURIComponent('$urlcloud');

var flashvars = {
	image: 'vid2.png'
};

var so1 = new SWFObject('./js/player.swf', 'mpl', '800', '600', '9');
so1.addParam('allowfullscreen','true');
so1.addVariable('streamer','rtmpe://{$distributionDomain}/cfx/st');
so1.addVariable('file', video);
so1.addVariable("autostart", "false");
so1.addVariable('image', 'vid2.png');
so1.write('flv{$item}');
</script> 

EOF;
}


	$res = $s3->list_objects(NINJA_STREAMING_BUCKET);
	
	$html = '';
	foreach ($res->body->Contents as $obj)
	{
		$key = (string)$obj->Key;
		$embed = getEmbed($key, NINJA_STREAMING_DOMAIN_NAME);
		$html .= $embed;
	}
	
	
	

//$embed = getEmbed('movie.mp4');

?>
<html>
<head>
</head>
<body>
<script type="text/javascript" src="./js/swfobject.js"></script>
<?php echo($html); ?>
</body>
</html>
