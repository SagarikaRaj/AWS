#!/bin/bash

export JAVA_HOME=/usr
export EC2_HOME=$HOME/Downloads/ec2tools
export AWS_ELB_HOME=$HOME/Downloads/ElasticLoadBalancing
export PATH=$PATH:$EC2_HOME/bin:$AWS_ELB_HOME/bin
export AWS_ACCESS_KEY=
export AWS_SECRET_KEY=


ec2-run-instances ami-c30360aa -g default -k 544newkey --instance-type t1.micro -n 2 --availability-zone us-east-1c -f install.sh
echo "EC2 instances Created. Sleeping for 200 seconds...";
sleep 200;

#ec2-describe-instances --filter "instance-state-code=16" | grep ec2.*amazonaws.com
# ssh ubuntu@ec2-54-205-95-53.compute-1.amazonaws.com -i 544newkey.priv


#Sagarika
#create an array to store the instance ids of each of the ec2 instances launched
declare -a INSTANCES
INSTANCES=(`ec2-describe-instances --filter "instance-state-code=16" | grep   ec2.*amazonaws.com | awk {'print $2'}`)


#creating a load balancer with name sagarikaLoad
export ELBVAR=`sudo elb-create-lb LoadBalancerName  sagarikaLoad  --listener lb-port=80,instance-port=80,protocol=http --availability-zones us-east-1c, -I $AWS_ACCESS_KEY -S $AWS_SECRET_KEY --debug|awk {'print $2'}`

# Output LoadBalancerName-1091136371.us-east-1.elb.amazonaws.com
echo "Load Balancer created. Sleeping 100 seconds...";
sleep 100;


#Health Check
sudo elb-configure-healthcheck LoadBalancerName sagarikaLoad --healthy-threshold 10 --interval 30 health_check --target http:80/index.php --timeout 5 --unhealthy-threshold 2 -I $AWS_ACCESS_KEY -S $AWS_SECRET_KEY 
echo "Health Check done. Sleeping 30 seconds..."
sleep 30;
# Output HEALTH_CHECK  http:80/index.php  30  5  10  2

#Register the load balancer with the 2 ec2 instances
sudo elb-register-instances-with-lb LoadBalancerName sagarikaLoad --instances ${INSTANCES[0]} ${INSTANCES[1]} -I $AWS_ACCESS_KEY -S $AWS_SECRET_KEY
echo "Load balancer registered. Sleeping 20 seconds";
sleep 20;
# Output :INSTANCE_ID  i-7ce75318
#	  INSTANCE_ID  i-20e75344

echo "Opening Firefox with Load Balancer";
#Restart firefox with the DNS of the ELBVAR
firefox $ELBVAR & 



 




