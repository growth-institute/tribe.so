![Tribe.so for PHP](https://i.imgur.com/ydKvAlA.png)

# Tribe.so for PHP

## What is this?

This library is a PHP tool to integrate [Tribe](https://www.tribe.so/) API into your projects. Tribe.so documentation can be found [here](https://partners.tribe.so/docs/guide/index/).

## How to use it

Simply require the package with `composer require growth-institute/tribe.so-php`.

Tribe API works with a GraphQL API, very different stuff than a REST API.

Don't forget to include your vendor `autoload.php`. Then you will be able to instantiate a Tribe object. The constructor receives the _Tribe APP Bearer_ as its only parameter. Example:

```php
<?php

	include('vendor/autoload.php');
	use TribePHP\Tribe;

	$tribe = new Tribe('your token here');

	$spaces = $tribe->getSpaces();

	echo "<pre>";
	print_r($spaces);
	echo "</pre>";
?>
```

Take a look to the src files to check all the functions available.