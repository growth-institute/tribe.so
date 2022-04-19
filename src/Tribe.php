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

		public function getAppToken($networkId, $url, $memberId = ''){
			$arguments = [
				"context" => "NETWORK",
				"networkId" => $networkId, 
				"entityId" => $networkId
			];

			if($memberId) $arguments["impersonateMemberId"] = $memberId;

			$params = ["accessToken"];
			$instance = new Graph('limitedToken', $arguments);
			$query = $instance->root()->use('accessToken')->query();	
			$query = str_replace('"NETWORK"',"NETWORK", $query);
			$curl = curl_init();
			$graph_params = [
				'query' => $query
			];
			curl_setopt_array($curl, [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => json_encode($graph_params),
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json'
				],
			]);
			$response = curl_exec($curl);
			curl_close($curl);
			return json_decode($response);
		}

		public function joinSpace($user_access_token, $space_id){
			$arguments = [
				"spaceId" => $space_id
			];

			$params = [
				"status"
			];

			$mutation = new Mutation('joinSpace');

			$query = $mutation->joinSpace($arguments)->use('status')->root()->query();
			$curl = curl_init();
			$graph_params = [
				'query' => $query
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
					'Authorization: Bearer ' . $user_access_token,
					'Content-Type: application/json'
				],
			]);
			$response = curl_exec($curl);
			curl_close($curl);
			return json_decode($response);
		}
		public function getMembers(){
			$arguments = [
				"limit" => 100000
			];
			$params = [
				"totalCount",
				"nodes" => [
					"id",
					"name",
					"email",
					"username"
				]
			];
			$instance = new Graph('members', $arguments);
			$query = $this->generateNodeFields($instance, $params);
			$query = $instance->root()->query();			
			$response = $this->request($query);
			return $response->data;
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
		private function createInstanceName($name, $fields = [], $variables = [], $params = [], $instance) {

			$mutation = new Mutation($name, array_merge($fields, [ 'input'=> new Variable('input', $instance . 'Input!', '') ]));

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

		public function getPosts($arguments, $params) {

			$instance = new Graph('posts', $arguments);
			$query = $this->generateNodeFields($instance, $params);
			$query = $instance->root()->query();			
			$response = $this->request($query);
			return $response->data->posts;
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
		}

		public function createPost($space_id, $title, $content, $params = [], $fields = []) {

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

		public function getSpaces($arguments, $params){
			$instance = new Graph('spaces', $arguments);
			$query = $this->generateNodeFields($instance, $params);
			$query = $instance->root()->query();			
			$response = $this->request($query);
			return $response->data;
		}
		public function getCollections($params){
			$instance = new Graph('collections');
			$query = $this->generateNodeFields($instance, $params);
			$query = $instance->root()->query();			
			$response = $this->request($query);
			return $response;
		}
		public function updateSpace($params, $variables, $input) {

			$input = [
				'input' => $input
			];

			return $this->createInstance('updateSpace', $variables, $input, $params);
		}

		public function createReply($post_id, $content, $params = [], $fields = []) {

			$fields = array_merge(['postId' => $post_id], $fields);

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

			return $this->createInstanceName('createReply', $fields, $variables, $params, "CreatePost");
		}
		public function createSpace($params = [], $variables = [], $fields = []) {
			/*  Params lo que quieres saber */
			/*  Fields parametros de un solo nivel*/
			/*  Variables parametros de varios niveles */
			$variables = [
				'input' => $variables
			];
			return $this->createInstance('createSpace', $fields, $variables, $params);
		}

		public function deleteSpace($params = [], $variables = [], $fields = []){

			$params = [
				"status"
			];

			$mutation = new Mutation('deleteSpace');

			$query = $mutation->deleteSpace($variables)->use('status')->root()->query();
			$response = $this->request($query);
			return $response;
		}
	}
?>