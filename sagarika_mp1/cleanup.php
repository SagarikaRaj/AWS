<?php session_start();

// Include the SDK using the Composer autoloader
require 'vendor/autoload.php';

use Aws\Common\Aws;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient;
use Aws\Sns\SnsClient;
use Aws\Sns\Exception\InvalidParameterException;

$aws = Aws::factory('/var/www/vendor/aws/aws-sdk-php/src/Aws/Common/Resources/custom-config.php');
$sqsclient=$aws->get('Sqs');
$snsclient=$aws->get('sns');
$client = $aws->get('S3');

################################################################################
# code to consume the Queue to make sure the job is done
################################################################################
echo "Consuming SQS ";
echo "<br>";
// receive the message using receiveMessage and messageBody
$itemName=$_SESSION['itemName'];
$mbody="";

try
{
	$queueUrl=$_SESSION['queueUrl'];  //queueUrl from resize.php 
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
}
catch(Exception $ex)
{
	echo "Exception in receiving message from Queue!".$ex->getmessage();
	echo "<br>";
}
$indexMsgQueue = 0;
$indexReceiptHandler=0;
//get the index in the MessageBody array at which the message to be deleted is present
if($result != null)
{
	foreach ($result->getPath('Messages/*/Body') as $messageBody) 
	{
	    $indexMsgQueue++;
	    if($itemName==$messageBody)
	    $mbody=$messageBody;
	}
}
echo "The SQS body is".$mbody;
echo "<br>";
//echo "the index at which the message body is found in queue is ".$indexMsgQueue ;
//echo "<br>";

//try getting the receipt handler from message.ReceiptHandle using messageBody and queueUrl instead
//another alternate approach is to delete by userid and time uploaded

//go to the same index as the required messageBody in the array to ReceiptHandle and delete it
foreach ($result->getPath('Messages/*/ReceiptHandle') as $recHandle)
{
	$indexReceiptHandler++;
	if($indexReceiptHandler == $indexMsgQueue)
	{
		//echo "Receipt Handler of the SQS message being deleted is ".$recHandle;
		$deleteResult=$sqsclient->deleteMessage(array(
		    'QueueUrl' => $queueUrl,
		    'ReceiptHandle' => $recHandle));
		echo "Message Deleted!";
		echo "<br>";
	}
}
echo "##############################################";
echo "<br>";
################################################################################
# Set object expire for S3 containing the resized image
################################################################################
echo "S3: Setting Bucket Life Cycle on the bucket containing resized image";
echo "<br>";
#create an object expiration configuration 
$resized=$_SESSION['resized'];
echo "Value of bucket is: ".$resized;
echo "<br>";
try
{
	$result = $client->putBucketLifecycle(array(
		'Bucket' => $resized,
		'Rules' => array(
    		 array(
			'Expiration' => array(
			'Days' =>1,
			),
		
		  'Prefix' => 'resized',
		   'Status' => 'Enabled',
		)			
	)));
	echo "bucket life cyle set to 1 day ";
	echo "<br>";

}
catch (Exception $e)
{
	echo "Exception while setting bucket expiration".$e->getMessage();
	echo "<br>";
}
echo "##############################################";
echo "<br>";
#####################################################
# Setting ACL to the resized object in s3
#####################################################
echo "S3: Setting Permissions";
echo "<br>";
try
{
	$result=$client->putObjectAcl(array(
	'Bucket' => $resized,
	'Key' => $resized,
	'ACL' => 'public-read',
	));
	echo "Object Permission set to Public-read ";
	echo "<br>";

}
catch(Exception $e)
{
	echo "There was an exception while giving permission to the Modified Image".$e->getmessage();
	echo "<br>";
}
echo "##############################################";
echo "<br>";
#####################################################
# SNS Sending SMS with finished URL
#####################################################
$emailForSNS=$_SESSION['email']; 
$phoneNoForSNS=$_SESSION['phone'];
$topic = explode("-",$emailForSNS );
$resizedS3url = $_SESSION['resizedS3url'];

try 
{
  $result = $snsclient->createTopic(array(
      'Name' => $topic[0],  // Name is required
  ));

$topicArn = $result['TopicArn'];

echo $topicArn;
echo $phoneNoForSNS;

try
{
    $result = $snsclient->setTopicAttributes(array(
    'TopicArn' => $topicArn,    //TopicArn is required
    'AttributeName' => 'DisplayName', //AttributeName is required
    'AttributeValue' => 'aws544:FinishedURL',
     ));
}
catch(Exception $exc) 
{
	 echo 'Error while setting SNS attributes: '. $exc->getMessage();
	 echo "<br>";
}


$result = $snsclient->subscribe(array(
    'TopicArn' => $topicArn, //TopicArn is required
    'Protocol' => 'sms',     // Protocol is required
    'Endpoint' => $phoneNoForSNS,
     )); 
	echo "SMS Sent for Subscribing";
	echo "<br>";
} 
catch(InvalidParameterException $invParam) 
{
	echo 'Invalid parameter in SNS: '.$invParam->getMessage();
	echo "<br>";
} 

//publishing SMS through SNS
try
{
	//$topicArn='sagarika-finished';
	$result = $snsclient->publish(array(
	    'TopicArn' => $topicArn,
	    'TargetArn' => $topicArn,    
	    'Message' => 'Your image has been resized: '.$resizedS3url,// Message is required
	    //'Subject' => $resizedS3url,
	    'MessageStructure' => 'sms',
	));
	echo "SMS Sent with finished URL";
	echo "<br>";
}
catch (Exception $e)
{
	echo 'Problem publishing SMS '.$e->getMessage();
	echo "<br>";
}

echo "##############################################";
echo "<br>";

?> 
