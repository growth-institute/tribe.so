![Circle.so for PHP](https://i.imgur.com/xX7p6zl.png)

# Circle.so for PHP

## What is this?

This library is a PHP tool to integrate [Circle](https://www.circle.so/) API into your projects. Circle.so documentation can be found [here](https://api.circle.so).

## How to use it

Simply require the package with `composer add growth-institute/circle.so-php`.

Don't forget to include your vendor `autoload.php`. Then you will be able to instantiate a Circle object. The constructor receives the _Circle API Key_ as its only parameter. Example:

```php
<?php

	include('vendor/autoload.php');
	use CirclePHP\Circle;

	$circle = new Circle('your token here');

	$communities = $addevent->communities();

	echo "<pre>";
	print_r($communities);
	echo "</pre>";
?>
```

Take a look to the src files to check all the functions available.