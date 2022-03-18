<?php

	namespace TribePHP;

	use Curl\Curl;
	use GraphQL\Graph;
	use GraphQL\Mutation;
	use GraphQL\Variable;

	class Tribe {

		private $api_key;
		private $community_id;

		public function __construct($api_key) {
			$this->api_key = $api_key;
			$this->baseUrl = 'https://app.tribe.so/graphql';
		}

		public function getAppToken($networkId){
/* 			query {
				limitedToken(
				  context:NETWORK, 
				  networkId: "{networkId}", 
				  entityId: "{networkId}", 
				  impersonateMemberId: "{memberId}"
				) {
				  accessToken
				}
			  } */
			  $instance = new Graph('limitedToken', $arguments);
			  $query = $this->generateNodeFields($instance, ['accessToken']);
			  $query = $instance->root()->query();			
			  $response = $this->request($query);
			  return $response;

		}
		private function request($query, $variables = []) {

			$curl = curl_init();

			$graph_params = [
				'query' => $query,
				'variables' => $variables
			];

			curl_setopt_array($curl, [
				CURLOPT_URL => $this->baseUrl,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => json_encode($graph_params),
				CURLOPT_HTTPHEADER => [
					'Authorization: Bearer ' . $this->api_key,
					'Content-Type: application/json'
				],
			]);
			/* print($query);
			print($variables); */

			$response = curl_exec($curl);

			curl_close($curl);

			return json_decode($response);
		}

		private function generateNodeFields($nodes, $fields) {

			foreach($fields as $k => $field) {
				if(is_int($k)) {
					$nodes = $nodes->use($field);
				} else {

					$nodes = $nodes->$k;
					if(is_array($field)) {

						$nodes = $this->generateNodeFields($nodes, $field);
					}
				}
			}

			return $nodes;
		}

		private function getCollection($name, $fields = [], $params = [], $defaults = []) {

			$instance = new Graph($name, array_merge($params, $defaults));

			$instance->nodes
				->prev()
				->use('totalCount');

			$nodes = $instance->nodes;

			$nodes = $this->generateNodeFields($nodes, $fields);

			$query = $instance->root()->query();
			$response = $this->request($query);

			if(isset($response->data->$name->nodes)) {

				return $response->data->$name->nodes;
			}

			return $response;
		}

		private function createInstance($name, $fields = [], $variables = [], $params = []) {

			$mutation = new Mutation($name, array_merge($fields, [ 'input'=> new Variable('input', ucwords($name) . 'Input!', '') ]));

			$query = $this->generateNodeFields($mutation, $params);

			$query = $query->root()->query();

			$response = $this->request($query, $variables);

			if(isset($response->data->$name)) {

				return $response->data->$name;
			}

			return $response;
		}

		private function mappingField($name, $type, $value) {

			if(in_array($type, ['text', 'html'])) $value = "\"{$value}\"";

			return [
				'key' => $name,
				'type' => $type,
				'value' => $value
			];
		}

		public function getPosts($space_ids, $fields = [], $params = []) {

			/*
				query {
					posts(limit: 100, spaceIds: ["0fihjgNW2UaH"]) {
						nodes {
							title,
							id,
							postTypeId
							postType {
								id
							}
						},
						totalCount
					}
				}
			*/

			if(!$params) {
				$params = ['limit' => 100];
			}

			if(!$fields) {
				$fields = [
					'id',
					'title',
					'postTypeId',
					'postType' => [
						'id'
					]
				];
			}

			//Check if space id is string or array
			if(is_string($space_ids)) $space_ids = [$space_ids];

			return $this->getCollection('posts', $fields, $params, ['spaceIds' => $space_ids]);
		}
<<<<<<< HEAD

		public function getSpaces($fields = [], $params = []) {

			if(!$params) {
				$params = ['limit' => 100];
			}

			if(!$fields) {
				$fields = [
					'id',
					'name',
					'postsCount'
				];
			}

			return $this->getCollection('spaces', $fields, $params);
		}

		public function getSpace($arguments, $params){
			$instance = new Graph('space', $arguments);
			$query = $this->generateNodeFields($instance, $params);
			$query = $instance->root()->query();			
			$response = $this->request($query);
			return $response;
		}

		public function getPost($arguments, $params){
			$instance = new Graph('post', $arguments);
			$query = $this->generateNodeFields($instance, $params);
			$query = $instance->root()->query();			
			$response = $this->request($query);
			return $response;
=======

		public function getSpaces($fields = [], $params = []) {

			if(!$params) {
				$params = ['limit' => 100];
			}

			if(!$fields) {
				$fields = [
					'id',
					'name',
					'postsCount'
				];
			}

			return $this->getCollection('spaces', $fields, $params);
>>>>>>> a8e89ce4418ed0422beda5534cc24a2da48a2c39
		}

		public function createPost($space_id, $title, $content, $params = [], $fields = []) {

<<<<<<< HEAD
			/*  Params lo que quieres saber */
			/*  Fields parametros de un solo nivel*/
			/*  Variables parametros de varios niveles */
=======
>>>>>>> a8e89ce4418ed0422beda5534cc24a2da48a2c39
			/*
			mutation($input: CreatePostInput!) {
				createPost(
					input: $input
					spaceId: "0fihjgNW2UaH"
				) {
					id
				}
			}

			mutation CreatePostMutation($input: CreatePostInput!) {
				createPost(
					spaceId: "0fihjgNW2UaH"
					input: $input
				) {
					id
				}
			}
			*/
<<<<<<< HEAD

			$fields = array_merge(['spaceId' => $space_id], $fields);

			$variables = [
				'input' => [
					'postTypeId' => 'udE3pz9DBGv7nsr',
					'publish' => true,
					'mappingFields' => [
						$this->mappingField('title', 'text', $title),
						$this->mappingField('content', 'html', $content)
					]
				]
			];

			return $this->createInstance('createPost', $fields, $variables, $params);
		}
		public function createSpace($params = [], $variables = [], $fields = []) {
			/*  Params lo que quieres saber */
			/*  Fields parametros de un solo nivel*/
			/*  Variables parametros de varios niveles */
			$variables = [
				'input' => $variables
			];
			return $this->createInstance('createSpace', $fields, $variables, $params);
=======

			$fields = array_merge(['spaceId' => $space_id], $fields);

			$variables = [
				'input' => [
					'postTypeId' => 'udE3pz9DBGv7nsr',
					'publish' => true,
					'mappingFields' => [
						$this->mappingField('title', 'text', $title),
						$this->mappingField('content', 'html', $content)
					]
				]
			];

			return $this->createInstance('createPost', $fields, $variables, $params);
>>>>>>> a8e89ce4418ed0422beda5534cc24a2da48a2c39
		}
	}
?>