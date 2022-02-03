<?php

	namespace TribePHP;

	use Curl\Curl;
	use GraphQL\Graph;

	class Tribe {

		private $api_key;
		private $community_id;

		public function __construct(string $api_key) {

			$this->api_key = $api_key;
			$this->baseUrl = 'https://app.tribe.so/graphql';
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

		public function getPosts($space_ids, $fields = []) {

			/*
				query {
			    posts(limit: 100, spaceIds: ["0fihjgNW2UaH"]) {
			        nodes {
			            title,
			            id,
			            postTypeId
			        },
			        totalCount
			    }
			}
			*/

			if(!$fields) {

				$fields = ['id', 'title', 'postTypeId'];
			}


			if(is_string($space_ids)) $space_ids = [$space_ids];


			$posts = new Graph('posts', ['limit' => 100, 'spaceIds' => $space_ids]);


			$query = $posts->nodes
						->use('title', 'id', 'postTypeId')
						->prev()
						->use('totalCount')
						->root()
							->query();

			echo $query;
			exit;

			$response = $this->request($query);


			if(isset($response->data->posts->nodes)) {

				return $response->data->posts->nodes;
			}

			return false;
		}
	}
?>