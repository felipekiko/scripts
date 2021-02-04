<?php
require 'aws-sdk-php/aws-autoloader.php';
 
use Aws\Ec2\Ec2Client;
use Aws\CloudWatch\CloudWatchClient;
 
define("STATE_OK", 0);
define("STATE_WARNING", 1);
define("STATE_CRITICAL", 2);
define("STATE_UNKNOWN", 3);
 
$ec2Client = new Ec2Client([
    'region' => 'us-east-1',
    'version' => 'latest',
    'profile' => 'PROFILE']);
 
$cloudWatchClient = CloudWatchClient::factory(array(
    'region' => 'us-east-1',
    'version' => 'latest',
    'profile' => 'PROFILE'));
 
$result = $ec2Client->describeInstances([
        'Filters' => [
                [
                        "Name" => "tag:Name",
                        "Values" => ["TAG1", "TAG2"]
                ],
                [
                        "Name" => "instance-state-code",
                        "Values" => ["16"]
                ]
        ]
]);
 
$reservations = $result['Reservations'];
 
$totalInstances = 0;
$terminatedInstances = 0;
$cpuMedian = 0;
$groupMetrics = array(1 => 0, 2 => 0, 3 => 0);
 
foreach ($reservations as $reservation) {
    $instances = $reservation['Instances'];
    foreach ($instances as $instance) {
        $metrics = $cloudWatchClient->getMetricStatistics(array(
                'Namespace'     => 'AWS/EC2',
                'MetricName'    => 'CPUUtilization',
                'Dimensions'    => array(array('Name' => 'InstanceId', 'Value' => $instance['InstanceId'])),
                'StartTime'     => strtotime('-5 minutes'),
                'EndTime'       => strtotime('-1 second'),
                'Period'        => 300,
                'Statistics'    => array('Average'),
                'Unit'          => 'Percent'
        ));
 
        $cpuPercent = "\033[0mN/A";
        if(isset($metrics['Datapoints'][0]['Average'])){
                $cpuPercent = round($metrics['Datapoints'][0]['Average']);
                if($cpuPercent <= 65) {
                        $color = "\033[32m";
                        $groupMetrics[1] = $groupMetrics[1]+1;
                } elseif($cpuPercent <= 85) {
                        $color = "\033[33m";
                        $groupMetrics[2] = $groupMetrics[2]+1;
                } else {
                        $color = "\033[31m";
                        $groupMetrics[3] = $groupMetrics[3]+1;
                }
        }
 
        echo "Instance ID: {$instance['InstanceId']} {$color}CPU {$cpuPercent}% \033[0m" . PHP_EOL;
 
        $totalInstances++;
        $cpuMedian += $cpuPercent;
    }
}
 
$clusterMedian = round($cpuMedian / $totalInstances);
$color = (($clusterMedian <= 65) ? "\033[32m" : (($clusterMedian <= 85) ? "\033[33m" : "\033[31m"));
echo PHP_EOL . "AWS EC2: {$totalInstances} instances with CPU median {$color}{$clusterMedian}% \033[0m" . PHP_EOL;
echo "\033[32m--> OK: {$groupMetrics[1]}" . PHP_EOL;
 
$state = STATE_OK;
 
if($groupMetrics[2] > 0){
        echo "\033[33m--> WARNING: {$groupMetrics[2]}" . PHP_EOL;
        $state = STATE_WARNING;
}
 
if($groupMetrics[3] > 0){
        echo "\033[31m--> CRITICAL: {$groupMetrics[3]}" . PHP_EOL;
        $state = STATE_CRITICAL;
}
 
echo "\033[0m";
 
echo PHP_EOL . "Instances to terminate";
echo PHP_EOL . "-------------------------------------------------------" . PHP_EOL;
 
foreach ($reservations as $reservation) {
    $instances = $reservation['Instances'];
    foreach ($instances as $instance) {
        $metrics = $cloudWatchClient->getMetricStatistics(array(
                'Namespace'     => 'AWS/EC2',
                'MetricName'    => 'CPUUtilization',
                'Dimensions'    => array(array('Name' => 'InstanceId', 'Value' => $instance['InstanceId'])),
                'StartTime'     => strtotime('-5 minutes'),
                'EndTime'       => strtotime('-1 second'),
                'Period'        => 300,
                'Statistics'    => array('Average'),
                'Unit'          => 'Percent'));
 
        if(isset($metrics['Datapoints'][0]['Average'])){
                $cpuPercent = round($metrics['Datapoints'][0]['Average']);
                if($cpuPercent == 100 && $terminatedInstances < 2) {
                        $output = '';

                        echo "Instance ID {$instance['InstanceId']} ({$instance['PublicIpAddress']}) will be terminated..." . PHP_EOL;
                        exec('aws ec2 terminate-instances --profile PROFILE --instance-ids ' . $instance['InstanceId'], $output);
                        echo $output . PHP_EOL;

                        $terminatedInstances++;
                }
        }
    }
}

echo "\033[0m";
