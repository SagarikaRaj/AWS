<!DOCTYPE html>
<html>
<head>
<title>Process</title>
<head><link rel="stylesheet" href="css/style1.css"></head>
</head>

<body>

<div class="navigation">  
  <div class="navbar">  
  	<ul class="nav">  
  		<li><a href="about.php">About</a></li>  
  		<li><a href="index.php">Home</a></li>  
  		<li><a href="resize.php">Resize</a></li>
		<li><a href="cleanup.php">Cleanup</a></li>   
   	</ul>  
  </div>  
</div>


<?php session_start();

// Include the SDK using the Composer autoloader
require 'vendor/autoload.php';

use Aws\Common\Aws;
use Aws\S3\S3Client;
use Aws\SimpleDb\SimpleDbClient;
use Aws\Sqs\SqsClient;
use Aws\Sns\SnsClient;
use Aws\Ses\SesClient;
use Aws\Sns\Exception\InvalidParameterException;


//aws factory
$UUID = uniqid();
$aws = Aws::factory('/var/www/vendor/aws/aws-sdk-php/src/Aws/Common/Resources/custom-config.php');
$client = $aws->get('S3');
$sdbclient=$aws->get('SimpleDb');
$snsclient=$aws->get('sns');
$sesclient = $aws->get('ses'); 
$sqsclient=$aws->get('Sqs');
$bucket = "OriginalImage".time(); 


//session variables so that these can be used in resize.php
$email = str_replace("@","-",$_POST["email"]); 
$_SESSION['email']=$email;
$phone = $_POST["phone"];
$_SESSION['phone']=$phone;

$topic = explode("-",$email );
$itemName = 'images-'.$UUID;
$_SESSION['itemName']=$itemName;
$topicArn='sagarika';


$recipients = $_POST["email"];
$emailSubject='Your image has been loaded successfully!';

#############################################
# Create SNS Simple Notification Service Topic for subscription
##############################################
echo "Starting SNS Service..";
echo "<br>";

try 
{
  $result = $snsclient->createTopic(array(
      'Name' => $topic[0],  // Name is required
  ));

$topicArn = $result['TopicArn'];


try
{
    $result = $snsclient->setTopicAttributes(array(
    'TopicArn' => $topicArn,    //TopicArn is required
    'AttributeName' => 'DisplayName', //AttributeName is required
    'AttributeValue' => 'aws544Initial URL:',
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
    'Endpoint' => $phone,
     )); 
	
  echo "SNS subscribed for number: ".$phone;
  echo "<br>";	
  echo "##############################################";
  echo "<br>";		
} 
catch(InvalidParameterException $invParam) 
{
	echo 'Invalid parameter in SNS: '.$invParam->getMessage();
	echo "<br>";
} 

###############################################################
# Create S3 bucket
############################################################
echo "S3 Service Starts";
echo "<br>";

$result = $client->createBucket(array(
    'Bucket' => $bucket
   ));

$client->waitUntil('BucketExists', array('Bucket' => $bucket)); // Wait until the bucket is created
$uploadDir = '/tmp/';
$uploadFile = $uploadDir.basename($_FILES['file-upload']['name']);


if (move_uploaded_file($_FILES['file-upload']['tmp_name'], $uploadFile))
{
    echo "File is valid, and was successfully uploaded ";
    echo "<br>";	
} 
else
{
    echo "File upload FAILED! Please retry from Home page";
    echo "<br>";
}
$pathToFile = $uploadDir.$_FILES['file-upload']['name']; // $pathToFile should be absolute path to a file on disk


// Upload an object by streaming the contents of a file
$result = $client->putObject(array(
    'ACL'        => 'public-read',
    'Bucket'     => $bucket,
    'Key'        => $_FILES['file-upload']['name'],
    'SourceFile' => $pathToFile,
    'Metadata'   => array(
        'timestamp' => time(),
        'md5' =>  md5_file($pathToFile),
    )
));
   echo "File upload into S3 success";
   echo "<br>";	

var_export($result->getkeys()); //gets all the key value pairs and exports them as system variables 

$url= $result['ObjectURL'];
echo "<br>";
echo "Initial url of S3 is".$url;
echo "<br>"; 
print "##############################################";
echo "<br>";
####################################################
# SimpleDB create here 
###################################################
$result = $sdbclient->createDomain(array(   
                'DomainName' => 'itm544sag',  // DomainName is required
   ));

try
{
	echo "Setting Simple DB attributes";
	echo "<br>";
	
	$result = $sdbclient->putAttributes(array(
    	    'DomainName' => 'itm544sag',// DomainName is required
	    'ItemName' =>$itemName ,   // ItemName is required
	    'Attributes' => array(     // Attributes is required
			        array('Name' => 'rawurl', 'Value' => $url),
			        array('Name' => 'bucket', 'Value' => $bucket),
			        array('Name' => 'id', 'Value' => $UUID),
			        array('Name' =>  'email', 'Value' => $email),
			        array('Name' => 'phone', 'Value' => $phone),
			        array('Name' => 'finishedurl', 'Value' => '','Replace' => true),     
			        array('Name' => 'filename','Value' => basename($_FILES['file-upload']['name']))
            		),
	));
	echo "Simple DB attributes setting success";
	echo "<br>";
	echo "##############################################";
  	echo "<br>";	

}
catch(Exception $ex)
{
	echo $ex->getMessage()."Exception while creating sdb client";
	echo "<br>";
}


$exp="select * from  itm544sag";

$result = $sdbclient->select(array(
    'SelectExpression' => $exp 
));

foreach ($result['Items'] as $item)
{
    var_export($item['Attributes']);
}
#####################################################
# SNS publishing of message to topic - which will be sent via SMS
#####################################################
$result = $snsclient->publish(array(
    'TopicArn' => $topicArn,
    'TargetArn' => $topicArn,    
    'Message' => 'Your image has been uploaded',// Message is required
    'Subject' => $url,
    'MessageStructure' => 'sms',

));
echo "SMS sent with initial URL of image in S3 ";
echo "<br>";
echo "##############################################";
echo "<br>";
#####################################################
# SQS publishing of message to Queuing which will be used by resize.php.
#####################################################
echo "SQS Service starts";
echo "<br>";
$urlList=$sqsclient->listQueues(array(
	'QueueNamePrefix' => 'Sagarika_Queue'
	));

if(isset($urlList['QueueUrls'][0]))
{
	$queueUrl=$urlList['QueueUrls'][0];
}

else
{
	$queueResult=$sqsclient->createQueue(array(
		'QueueName' => 'Sagarika_Queue',
	));
	$queueUrl=$queueResult['QueueUrl'];
}

$result = $sqsclient->sendMessage(array(
    'QueueUrl'    => $queueUrl,
    'MessageBody' => $itemName,
	'DelaySeconds' => 15,
));
echo "Message Sent to SQS ";
echo "<br>";
#####################################################
# SES for sending Email
#####################################################
echo "###########################################";
echo "<br>";
echo "SES Service Starts";
echo "<br>";

$sesResultList=$sesclient->listIdentities(array(
	'IdentityType' => "EmailAddress",
	'MaxItems' => 50,
));

$list_items=$sesResultList['Identities'];
$verified=false;

foreach($list_items as $emailList)
{ 
	if($emailList==$recipients)
		 $verified=true;
} 

$emailBody = "Your image has been successfully uploaded! You can view it at   ".$url;

if($verified)
{
	$sesResultList=$sesclient->sendEmail(array(
	'Source' => 'smuniraj@hawk.iit.edu',
	'Destination' => array(
	        'ToAddresses' => array($recipients)),
	'Message' => array(
        'Subject' => array(         // Subject is required
            'Data' => $emailSubject,
            ),
        'Body' => array(         // Body is required
            'Text' => array(
                'Data' => $emailBody,
                 ),
             ),
         ),
));
echo "Email successfully sent";
echo "<br>";	
}

else
{
	echo "Could not verify the email address given!";
	echo "<br>";
}

echo "##############################################";
echo "<br><br><br>";	
sleep(25);//wait for 25 seconds before redirecting to resize.php
?>
<script>
document.write('<div>Please wait while loading "Resize"... </div>');
window.location = 'resize.php';
</script>
</body>

</html>
