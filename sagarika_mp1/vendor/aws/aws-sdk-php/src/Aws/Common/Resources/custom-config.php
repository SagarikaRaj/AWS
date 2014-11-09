<?php
/**
 * Copyright 2010-2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

return array(
'includes' => array('_aws'),
    'services' => array(

        'default_settings' => array(
            'params' => array('key' => '',
                'secret' => '',
                'region' => 'us-east-1')
        ),

        'autoscaling' => array(
            'alias'   => 'AutoScaling',
            'extends' => 'default_settings',
            'class'   => 'Aws\AutoScaling\AutoScalingClient'
        ),

        'cloudwatch' => array(
            'alias'   => 'CloudWatch',
            'extends' => 'default_settings',
            'class'   => 'Aws\CloudWatch\CloudWatchClient'
        ),

        'dynamodb' => array(
            'alias'   => 'DynamoDb',
            'extends' => 'default_settings',
            'class'   => 'Aws\DynamoDb\DynamoDbClient'
        ),

        'dynamodb_20111205' => array(
            'extends' => 'dynamodb',
            'params' => array(
                'version' => '2011-12-05'
            )
        ),

        'ec2' => array(
            'alias'   => 'Ec2',
            'extends' => 'default_settings',
            'class'   => 'Aws\Ec2\Ec2Client'
        ),

        'elasticloadbalancing' => array(
            'alias'   => 'ElasticLoadBalancing',
            'extends' => 'default_settings',
            'class'   => 'Aws\ElasticLoadBalancing\ElasticLoadBalancingClient'
        ),

        'rds' => array(
            'alias'   => 'Rds',
            'extends' => 'default_settings',
            'class'   => 'Aws\Rds\RdsClient'
        ),

        'redshift' => array(
            'alias'   => 'Redshift',
            'extends' => 'default_settings',
            'class'   => 'Aws\Redshift\RedshiftClient'
        ),

        
        's3' => array(
            'alias'   => 'S3',
            'extends' => 'default_settings',
            'class'   => 'Aws\S3\S3Client'
        ),

        'sdb' => array(
            'alias'   => 'SimpleDb',
            'extends' => 'default_settings',
            'class'   => 'Aws\SimpleDb\SimpleDbClient'
        ),

        'ses' => array(
            'alias'   => 'Ses',
            'extends' => 'default_settings',
            'class'   => 'Aws\Ses\SesClient',
            'region' => 'us-east-1'
        ),

        'sns' => array(
            'alias'   => 'Sns',
            'extends' => 'default_settings',
            'class'   => 'Aws\Sns\SnsClient'
        ),

        'sqs' => array(
            'alias'   => 'Sqs',
            'extends' => 'default_settings',
            'class'   => 'Aws\Sqs\SqsClient',
            'region' => 'us-east-1'
        ),

        'swf' => array(
            'alias'   => 'Swf',
            'extends' => 'default_settings',
            'class'   => 'Aws\Swf\SwfClient'
        ),
    )
);
