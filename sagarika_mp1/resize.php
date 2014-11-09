<!DOCTYPE html>
<html>
<head>
<title>Resize</title>
<head><link rel="stylesheet" href="css/style1.css"></head>
</head>

<body>

<div class="navigation">  
  <div class="navbar">  
  	<ul class="nav">  
  		<li><a href="about.php">About</a></li>  
  		<li><a href="index.php">Home</a></li>  
		<li><a href="cleanup.php">Cleanup</a></li>   
   	</ul>  
  </div>  
</div>
</body>
</html>


<?php session_start();

// Include the SDK using the Composer autoloader
require 'vendor/autoload.php';

use Aws\SimpleDb\SimpleDbClient;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use Aws\Common\Aws;
use Aws\SimpleDb\Exception\InvalidQueryExpressionException;
use Aws\Sns\SnsClient;
use Aws\Sns\Exception\InvalidParameterException;

//aws factory
$aws = Aws::factory('/var/www/vendor/aws/aws-sdk-php/src/Aws/Common/Resources/custom-config.php');
// Instantiate required clients
$client = $aws->get('S3');
$s3client = $aws->get('S3');
$sdbclient = $aws->get('SimpleDb');
$sqsclient = $aws->get('Sqs');
$snsclient = $aws->get('sns');
$mbody="";

// Declare some variables for the selected object
$email = '';
$rawurl = '';
$finishedurl = '';
$bucket = '';
$id = '';
$phone = '';
$filename = '';
$localfilename = ""; //local variable used to store the content of the s3 object
$resizedimage= "";
$resized= "";
$resizedS3url="";

#####################################################
# SQS Read the queue for some information -- we will consume the queue later
#####################################################
echo "Using SQS Service ";
echo "<br>";
//get the queue url
try
{
	$result=$sqsclient->getQueueUrl(array(
			'QueueName' => 'Sagarika_Queue',
		));
	
}
catch(Exception $e)
{
	echo "Problem retrieving queue url! ".$e->getMessage();
	echo "<br>";
}

//getting the value of queue URL created
try
{
	$queueUrl=$result['QueueUrl'];
	$_SESSION['queueUrl']=$queueUrl;

}
catch(Exception $e)
{
	echo "Exception while reading Queue name".$e->getMessage();
	echo "<br>";
}

try
{
	$result = $sqsclient->receiveMessage(array(
    	// QueueUrl is required
    	'QueueUrl' => $queueUrl, 
    	'MaxNumberOfMessages' => 10, 
    	'WaitTimeSeconds' => 15
	));

	echo "Receiving message from Queue Successful! ";
	echo "<br>";
}
catch(Exception $ex)
{
	echo "Exception in receiving message from Queue!".$ex->getmessage();
	echo "<br>";
}
echo "##############################################";
echo "<br>";

######################################3
# Functionality - 
######################################

if($result != null)
{
	foreach ($result->getPath('Messages/*/Body') as $messageBody) 
	{
	    $mbody=$messageBody;
	}
	
	if($mbody!=null)
	{
        //getting attributes from SDB
	try
	{
		$result=$sdbclient->getAttributes(array( 
		'DomainName'=> 'itm544sag',
		'ItemName' => $mbody
		));
	}
	catch(Exception $e)
	{
		echo "Can't get attributes from SDb".$e->getmessage();
		echo "<br>";
	}

	try
	{
		foreach($result['Attributes'] as $item)
		{
			switch($item['Name'])
			{
				case "id":
					$id=$item['Value'];
					break;
				case "email":
					$email=$item['Value'];
					break;
				case "bucket":
					$bucket=$item['Value'];
					break;
				case "rawurl":
					$rawurl=$item['Value'];
					break;
				case "finishedurl":
					if(isset($item['Value']))
					$finishedurl=$item['Value'];
					break;
				case "filename":
					$filename=$item['Value'];
					break;
				case "phone":
					$phone=$item['Value'];
					break;
			}
		}
	echo "Retrieved Queue attributes succesfully";
	echo "<br>";
		
	}
	catch(Exception $e)
	{
		echo "Error while getting attributes".$e->getmessage();
		echo "<br>";
	}
echo "##############################################";
echo "<br>";
###########################################################################
# Now that we have the URI returned in the S3 object, we can use wget to pull down the image from the S3 url.
# Then add stamp and reupload it to S3.
# Then update the item in SimpleDb  S3 has a prefix URL which can be hard coded https://s3.amazonaws.com
############################################################################
	echo "Retrieve from S3 starts";
	echo "<br>";
	$s3urlprefix = 'https://s3.amazonaws.com/';
	$localfilename = "/tmp/".$filename;
	
	try
	{
		$result = $client->getObject(array( //getting the S3 object and downloading it to local file system
		    'Bucket' => $bucket,
		    'Key'    => $filename,
		    'SaveAs' => $localfilename,
		));
		echo "S3 Object downloaded to local file system with name".$localfilename;
		echo "<br>";
	}
	catch(Exception $e)
	{
		echo "Exception while downloading the S3 obj and storing it on local disk ".$e->getMessage();
		echo "<br>";
	}
echo "##############################################";
echo "<br>";
	
############################################################################
#  Passing the S3 object downloaded to our watermark library http://en.wikipedia.org/wiki/Watermark -- using a function  
###########################################################################
	echo "Adding stamp to the downloaded S3 object";
	echo "<br>";	
	addStamp($localfilename);
        $_SESSION['resized']=$resized;
	echo "Stamp added";
	echo "<br>";
	echo "The new file location of the stamped image is".$resizedimage;
	echo "<br>";
	echo "The image name is".$resized;	
	echo "<br>";
	echo "##############################################";
	echo "<br>";
###############################################################
# Create S3 bucket for the resized object
############################################################
	echo "S3 Service for resized Image";
	echo "<br>";
	try
	{
		$result = $s3client->createBucket(array(
		    'Bucket' => $resized
		));
	}
	catch(Exception $e)
	{
		echo"Exception while creating S3 bucket ".$e->getmessage();
		echo "<br>";
	}
	try
	{
		$s3client->waitUntil('BucketExists', array('Bucket' => $bucket)); // Wait until the bucket is created
		$result = $s3client->putObject(array(
		    //'ACL'        => 'public-read',
		    'Bucket'     => $resized,
		    'Key'        => $resized,
		    'SourceFile' => $resizedimage,
		    'Metadata'   => array(
			'timestamp' => time(),
			'md5' =>  md5_file($resizedimage),
		    ),
		 ));
		echo "Putting the updated image in new S3 bucket Success! ";
		echo "<br>";
	}	
	catch(Exception $e)
	{
		echo "Exception while putting the updated image in new S3 bucket ".$e->getMessage();
		echo "<br>";
	}
		
	$resizedS3url= $result['ObjectURL'];
	echo "The value of resized Url is ".$resizedS3url;
	echo "<br>";
	$_SESSION['resizedS3url']=$resizedS3url;

	var_export($result->getkeys()); //gets all the key value pairs and exports them as system variables

	$newurl= $result['ObjectURL'];
	unlink($resizedimage);
	unlink($localfilename);
	echo "The value of bucket is".$bucket;
	echo "<br>";
	echo "The resized image url is".$newurl;
	echo "<br>";
	echo "##############################################";
	echo "<br>";

####################################################
# SimpleDB 
###################################################
	echo "Simple DB Service";
	echo "<br>";	
	$result = $sdbclient->createDomain(array(
	    	'DomainName' => 'itm544sag',  //DomainName is required
	));

	echo "The item name in Simple DB is".$mbody ;
	echo "<br>";
	try
	{
		$result = $sdbclient->putAttributes(array(
	    	    'DomainName' => 'itm544sag', //DomainName is required
		    'ItemName' =>$mbody, //ItemNane is required
		    'Attributes' => array( //Attributes is required
		     array('Name' => 'finishedurl', 'Value' => $newurl,'Replace' => true),     
		  ),
		));
		echo "Put resized attributes into Simple DB Success!";
		echo "<br>";
	}
	catch(exception $e)
	{
		echo"There was a exception while updating the finished url in simpledb".$e->getmessage();
		echo "<br>";
	}//end of catch
	echo "##############################################";
	echo "<br><br><br>";
}
sleep(30);//wait for 30 seconds before redirecting to cleanup.php
} //End of if condition - queue not null


#########################################################################
# PHP function for adding a "stamp" or watermark through the php gd library
#########################################################################
function addStamp($image)
{
	// Load the stamp and the photo to apply the watermark to the original image
	// http://php.net/manual/en/function.imagecreatefromgif.php

	global $resized,$resizedimage;
	try
	{
		$stamp = imagecreatefromgif('happy_trans.gif');
	}
	catch(Exception $e)
	{
		echo 'exception'.$e;
	}
    	
	$im = imagecreatefromjpeg($image);


	// Set the margins for the stamp and get the height/width of the stamp image
	$marge_right = 10;
	$marge_bottom = 10;
	$sx = imagesx($stamp);
	$sy = imagesy($stamp);

	// Copy the stamp image onto our photo using the margin offsets and the photo 
	// width to calculate positioning of the stamp. 
	$copy= imagecopy($im, $stamp, imagesx($im) - $sx - $marge_right, imagesy($im) - $sy - $marge_bottom, 0, 0, imagesx($stamp), imagesy($stamp));
	
	// Output and free memory
	$resized="resized".uniqid();
	$resizedimage="/tmp/".$resized.".png";
	imagepng($im,$resizedimage);  
	imagedestroy($im);
} // end of function



?>
<html>
<head><title>Resize PHP</title></head>
<body>
<img src="file.png"/>

<script>
document.write('<div>Please wait while loading "Cleanup"... </div>');
window.location = 'cleanup.php';
</script>

</body>
</html>
