<?php
echo 'AWS ACCESSKEY: ' . $argv[1] . PHP_EOL;
echo 'AWS SECRETKEY: ' . $argv[2] . PHP_EOL;
echo 'AWS BUCKET: ' . $argv[3] . PHP_EOL;
echo 'AWS FOLDER: ' . (!empty($argv[4])?$argv[4]:'-') . PHP_EOL;
echo PHP_EOL;

if(count($argv) == 4 || count($argv) == 5) {
	$folder = (!empty($argv[4])?'/'.$argv[4]:'');

	echo 'GERANDO ARQUIVO' . PHP_EOL;
	exec("s3cmd --access_key={$argv[1]} --secret_key={$argv[2]} ls s3://{$argv[3]}{$folder}/ --recursive > list.tmp");
	echo 'ARQUIVO GERADO' . PHP_EOL;

	echo 'LENDO ARQUIVO' . PHP_EOL;
	$file = file('list.tmp');
	$total = count($file);
	for($i = 0; $i < $total; $i++){
		$line = preg_replace('!\s+!m', ' ', trim(substr($file[$i], 35 + strlen($argv[3]))));
		echo ($i + 1) . '/' . count($file) . ': ' . $line;
		if(strpos($line, '_$folder$') !== false) {
			echo ' SKIPPED FOLDER' . PHP_EOL;
		}
		else {
			exec("aws s3api put-object-acl --bucket {$argv[3]} --key {$line} --acl private");
			echo ' OK' . PHP_EOL;
		}
	}

	echo 'PERMISSAO FINALIZADA';
	unlink('list.tmp');
}
