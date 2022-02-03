<?php

	include('vendor/autoload.php');
	use TribePHP\Tribe;

	function print_a($var) {
		echo "<pre>";
		print_r($var);
		echo "</pre>";
	}

	$post_with_mapping = [
		'id',
		'title',
		'postTypeId',
		'postType' => [
			'id',
			'name',
			'updatedAt',
			'mappings' => [
				'key',
				'title',
				'type',
				'field',
				'description'
			]
		]
	];

	$tribe = new Tribe('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IkFQUDo6aWY0ZUkyMnhXdDdaIiwibmV0d29ya0lkIjoiV0Zjd2J4TEdmUyIsInRva2VuVHlwZSI6IkxJTUlURUQiLCJlbnRpdHlJZCI6IldGY3dieExHZlMiLCJwZXJtaXNzaW9uQ29udGV4dCI6Ik5FVFdPUksiLCJwZXJtaXNzaW9ucyI6WyIqIl0sImlhdCI6MTY0MzgzMTI2MiwiZXhwIjoxNjQ2NDIzMjYyfQ.gena0w1wh4tlw1LEyhUPlfJ1jXP0rl0XiIpkfveHp60');
	//print_a($tribe->getPosts(['0fihjgNW2UaH'], $post_with_mapping));


	print_a($tribe->getSpaces());


?>