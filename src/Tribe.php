<?php

	use TribePHP\Tribe;
	use CHAPI\MessageQ;
	use Firebase\JWT\JWT;

	class TribeEndpoint extends CHAPI\Endpoint {
		function init() {
			$this->listJWT = false;
			$this->addRoute('sso', [$this, 'sso'], 'post');
			$this->addRoute('post/{id}', [$this, 'getPost'], 'get');
			$this->addRoute('replies/{id}', [$this, 'getReplies'], 'get');
			$this->addRoute('post', [$this, 'createPost'], 'post');
			$this->addRoute('reply', [$this, 'createReply'], 'post');
			$this->addRoute('space', [$this, 'createSpace'], 'post');
			$this->addRoute('join', [$this, 'joinSpace'], 'post');
			$this->addRoute('space/{id}', [$this, 'getSpace'], 'get');
			$this->addRoute('auth', [$this, 'getTribeAccessToken'], 'post');
			$this->addRoute('member/{email}', [$this, 'getMember'], 'get');
			$this->addRoute('update/space', [$this, 'updateSpace'], 'post');
			$this->addRoute('delete/spaces', [$this, 'deleteAllSpaces'], 'delete');
			$this->addRoute('collections', [$this, 'getCollections'], 'get');
			$this->addRoute('posts/{id}', [$this, 'getPosts'], 'get');
			$this->addRoute('update/post', [$this, 'updatePost'], 'post');
			$this->addRoute('image', [$this, 'triggerImageUpload'], 'post');
			$this->addRoute('update/image', [$this, 'uploadImage'], 'post');
		}

		/**
		 * @throws Exception
		 */

		static function triggerImageUpload() {
			$message = MessageQ::newInstance();
			$message->sendMessage(json_encode(["class" => "TribeEndpoint", "function" => "uploadImage", "params" => []]));
			$message->close();
		}

		function uploadImage() {
			global $app;
			$email = '';
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));

			if($this->request->post("email")) $email = $this->request->post("email");

			$arguments = [
				"contentType" => ""
			];
			$params = [
				"fields",
				"mediaDownloadUrl",
				"mediaId",
				"mediaUrl",
				"signedUrl"
			];

			$res_members = $tribe->getMembers($email);

			if(isset($res_members->data->errors)) {
				$status = $this->handleErrors($res_members->data->errors);
				$this->response->setStatus($status);
				$this->data = $res_members->data;
				log_to_file($res_members->data->errors, 'mq');
				if($this->request->post("email")) $this->respond();
				return json_encode($this->data);
			}

			$members = $res_members;

			$members_all_updated = [];
			$members_errors = [];

			if(isset($members->members->nodes)) {
				foreach($members->members->nodes as $value) {
					echo "Executing...\r";
					if(($value->profilePictureId == null && $email == '') || $email != '') {
						$user = Users::getByLogin($value->email);
						if(!$user) {
							$new_member_error_object = [
								'email' => $value->email,
								'error' => 'User not found by email in Dojo db'
							];
							log_to_file($new_member_error_object, 'mq');
							array_push($members_errors, json_encode($new_member_error_object));
							continue;
						}

						$avatar_url = 'https://sensei.growthinstitute.com/users/' . $user->id . '/avatar';
						try {
							$json = @file_get_contents($avatar_url); //getting the file content
							if($json == false) {
								throw new Exception('Something really gone wrong');
							}
						} catch(Exception $e) {
							log_to_file("Error with url, avatar not found:" . $avatar_url, 'mq');
							continue;
						}
						$finfo = new finfo(FILEINFO_MIME_TYPE);
						$image_type = $finfo->buffer($json);
						$arguments['contentType'] = $image_type;
						$upload_object = $tribe->createImages($arguments, $params);

						if(isset($upload_object->data->errors)) {
							$status = $this->handleErrors($upload_object->data->errors);
							log_to_file($upload_object->data->errors, 'mq');
							array_push($members_errors, json_encode($upload_object->data->errors));
							continue;
						}

						$curl = curl_init();
						$requestData = json_decode($upload_object->fields);
						$requestData->{'Content-type'} = $image_type;
						$requestData->{'file'} = file_get_contents($avatar_url);
						curl_setopt_array($curl, [
							CURLOPT_URL => $upload_object->signedUrl,
							CURLOPT_RETURNTRANSFER => true,
							CURLOPT_ENCODING => '',
							CURLOPT_MAXREDIRS => 10,
							CURLOPT_TIMEOUT => 0,
							CURLOPT_FOLLOWLOCATION => true,
							CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
							CURLOPT_CUSTOMREQUEST => 'POST',
							CURLOPT_POSTFIELDS => $requestData,
							CURLOPT_HTTPHEADER => [
							],
						]);
						$response = curl_exec($curl);
						curl_close($curl);
						if($response == '') {
							$params_member = [
								'profilePictureId',
								'email',
								'updatedAt',
								'username'
							];

							$fields_member = [
								"id" => $value->id
							];

							$variables_member = [
								'profilePictureId' => $upload_object->mediaId
							];

							$member_updated = $tribe->updateMember($variables_member, $params_member, $fields_member);

							if(isset($member_updated->errors)) {
								$status = $this->handleErrors($member_updated->errors);
								log_to_file($member_updated->errors, 'mq');
								array_push($members_errors, json_encode($member_updated->errors));
								continue;

							}
							echo "Executing..";
							array_push($members_all_updated, $member_updated);
						}

					}
				}
			}

			$this->properties['updated'] = $members_all_updated;
			$this->properties['total'] = sizeof($members_all_updated);
			$this->properties['errors'] = $members_errors;
			if($this->request->post("email")) {
				$this->result = 'success';
				$this->respond();
			}
			log_to_file($this->properties, 'mq');
			return json_encode($this->properties);
		}

		function getPosts($id) {
			global $app;
			$space_id = $id;
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$arguments = [
				"limit" => 100000,
				"spaceIds" => [$space_id]
			];
			$params = [
				"totalCount"
			];
			$this->data = $tribe->getPosts($arguments, $params);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			}

			$this->result = 'success';
			$this->respond();
		}

		function getCollections() {
			global $app;
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$params = [
				"id",
				"name",
				"slug"
			];
			$res = $tribe->getCollections($params);
			$this->data = $res;
			$this->result = 'success';
			$this->respond();
		}

		function deleteAllSpaces() {
			global $app;
			$collection_id = $this->request->post("collectionId");
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$arguments = [
				"limit" => 100000
			];
			$params = [
				"totalCount",
				"nodes" => [
					"id",
					"url",
					"collection" => [
						"id",
						"name"
					],
					"name",
					"slug"
				]
			];
			$res = $tribe->getSpaces($arguments, $params);

			// delete Spaces
			foreach($res->spaces->nodes as $value) {
				$arguments = [
					"id" => $value->id
				];

				$params = [
					"status"
				];

				$res2 = $tribe->deleteSpace($params, $arguments);
			}

			$this->data = $res;
			$this->result = 'success';
			$this->respond();

		}

		function updateSpace() {
			global $app;
			$space_id = $this->request->post("space_id");
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$params = [
				'id',
				'name',
				'private',
				'hidden',
				'inviteOnly',
				'slug'

			];

			$input = [
				'hidden' => (boolean )true,
				'private' => (boolean)true
			];
			$variables = [
				"id" => $space_id
			];
			$this->data = $tribe->updateSpace($params, $variables, $input);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			}
			$this->result = 'success';
			$this->respond();

		}

		function joinSpace() {
			global $app;
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$space_id = $this->request->post('spaceId');
			$member_id = $this->request->post('memberId');
			$token = GeneralOptions::getOption('tribe_member_token-' . $member_id);
			$response = $tribe->joinSpace($token, $space_id);
			$this->data = $response;
			if(isset($this->data->errors) && $this->data->data != null) {
				$status = $this->handleErrors($this->data->errors, $member_id);
				$this->response->setStatus($status);
				$this->respond();
			} else if(isset($this->data->errors) && $this->data->data == null) {
				$this->data = "Already joined to this space";
			}
			$this->result = 'success';
			$this->respond();
		}

		function sso() {
			global $app;
			$userData = [
				'sub' => $this->request->post('sub'), // user's ID in your product
				'email' => $this->request->post('email'),
				'name' => $this->request->post('name')
			];
			$tribe_object = get_item($app->getOption('keys'), 'tribe');
			$private_key = $tribe_object['private_key'];
			$this->data = JWT::encode($userData, $private_key, 'HS256');
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			}
			$url = 'https://growth-institute.tribeplatform.com/api/auth/sso?redirect_uri=/&jwt=' . $this->data;
			$curl_handle = curl_init();
			curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl_handle, CURLOPT_URL, $url);
			$url_content = curl_exec($curl_handle);
			curl_close($curl_handle);
			// $this->checkUserSpaces();
			$this->result = 'success';
			$this->respond();
		}


		function createSpace() {
			global $app;
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$params = [
				'id',
				'name',
				'private',
				'hidden',
				'inviteOnly',
				'slug'

			];

			$variables = [
				'name' => $this->request->post('name'),
				'hidden' => (boolean )false,
				'private' => (boolean)false,
				'inviteOnly' => (boolean)false,
				'collectionId' => $this->request->post('collectionId')
			];
			$this->data = $tribe->createSpace($params, $variables);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			}
			$this->result = 'success';
			$this->respond();
		}

		function getTribeAccessToken() {
			global $app;
			// Get our credentials
			$tribe_object = get_item($app->getOption('keys'), 'tribe');
			$network_id = $tribe_object['network_id'];
			$client_id = $tribe_object['client_id'];
			$client_secret = $tribe_object['client_secret'];
			$memberId = $this->request->post('memberId');
			// Execute just for the first time
			$res = Tribe::getAppToken($network_id, 'https://' . $client_id . ':' . $client_secret . '@app.tribe.so/graphql', $memberId);
			if(isset($res->data->limitedToken->accessToken) && GeneralOptions::setOption('tribe_member_token-' . $memberId, $res->data->limitedToken->accessToken)) {
				$this->data = 'Member access token set successfully';
			} else {
				$this->data = 'Error: Member Access Token not saved';
			}
			$this->result = 'success';
			$this->respond();
		}

		function getSpace($id) {
			global $app;
			$arguments = [
				'id' => $id
			];
			$params = [
				'id',
				'name',
				'slug'
			];
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$this->data = $tribe->getSpace($arguments, $params);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			}
			$this->data = $this->data->data->space;
			$this->result = 'success';
			$this->respond();
		}

		function updatePost() {
			global $app;
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));

			$post_id = $this->request->post('id');
			$content = $this->request->post('content');
			$params_mapping = [
				'id',
				'title',
				'postTypeId',
				'repliesCount',
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

			$this->data = $tribe->updatePost($post_id, $content, $params_mapping);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			} else {
				$this->result = 'success';
			}

			$this->respond();

		}

		function createPost() {
			global $app;
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$params_mapping = [
				'id',
				'title',
				'postTypeId',
				'repliesCount',
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
			$title = $this->request->post('title');
			$content = $this->request->post('content');
			$space_id = $this->request->post('space_id');
			$this->data = $tribe->createPost($space_id, $title, $content, $params_mapping);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			} else {
				$this->result = 'success';
			}
			$this->respond();
		}

		function createReply() {
			global $app;
			$member_id = $this->request->post('member_id');
			$tkn = GeneralOptions::getOption('tribe_member_token-' . $member_id);
			$tribe = new Tribe($tkn);
			$params_mapping = [
				'id',
				'title',
				'status'
			];
			$content = $this->request->post('content');
			$post_id = $this->request->post('post_id');
			$this->data = $tribe->createReply($post_id, $content, $params_mapping);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors, $member_id);
				$this->response->setStatus($status);
				$this->respond();
			} else {
				$this->result = 'success';
			}
			$this->respond();
		}

		function getPost($id) {
			global $app;
			$arguments = [
				'id' => $id
			];
			$params = [
				'id',
				'repliesCount',
				'mappingFields' => [
					'key',
					'type',
					'value'
				]
			];
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$this->data = $tribe->getPost($arguments, $params);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			}
			$refactor = array();
			foreach($this->data->post->mappingFields as $value) {
				$refactor[$value->key] = $value;
				$refactor[$value->key]->value = preg_replace('~^"?(.*?)"?$~', '$1', $refactor[$value->key]->value);
				$refactor[$value->key]->value = str_replace('"', "'", $refactor[$value->key]->value);
				$refactor[$value->key]->value = str_replace('\\', "", $refactor[$value->key]->value);
			}

			$this->data->post->mappingFields = $refactor;
			$this->data = $this->data->post;
			$this->result = 'success';
			$this->respond();
		}

		function getMember($email) {
			global $app;
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$members = $tribe->getMembers($email);
			$this->data = $members->members->nodes[0];
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->result = 'error';
				$this->respond();
			}
			$this->result = 'success';
			$this->respond();
		}

		function handleErrors($errors, $memberId = null) {
			$sensei_errors = array();
			$status = '';
			foreach($errors as $error) {
				switch($error->message) {
					case "Unauthorized":
						if($memberId != null) {
							array_push($sensei_errors, $this->setAccessToken($memberId));
						} else {
							array_push($sensei_errors, $this->setAccessToken());
						}
						$status = 401;
						break;
					case "Internal Server Error":
						array_push($sensei_errors, "Tribe Internal Server Error");
						$status = 500;
						break;
					default:
						$status = 500;
						break;
				}
			}
			$this->data = [
				"sensei errors: " => $sensei_errors,
				"tribe_errors: " => $errors
			];
			return $status;
		}

		function setAccessToken($member = null) {
			global $app;

			// Get our credentials
			$response = '';
			$tribe_object = get_item($app->getOption('keys'), 'tribe');
			$network_id = $tribe_object['network_id'];
			$client_id = $tribe_object['client_id'];
			$client_secret = $tribe_object['client_secret'];
			// Execute just for the first time
			if($member != null) {
				// Execute just for the first time
				$res = Tribe::getAppToken($network_id, 'https://' . $client_id . ':' . $client_secret . '@app.tribe.so/graphql', $member);
				if(isset($res->data->limitedToken->accessToken) && GeneralOptions::setOption('tribe_member_token-' . $member, $res->data->limitedToken->accessToken)) {
					$response = 'Member access token set successfully';
				} else {
					$response = 'Error: Member Access Token not saved';
				}

			} else {
				$res = Tribe::getAppToken($network_id, 'https://' . $client_id . ':' . $client_secret . '@app.tribe.so/graphql');
				if(isset($res->data->limitedToken->accessToken) && GeneralOptions::setOption('tribe_app_token', $res->data->limitedToken->accessToken)) {
					$response = "Unauthorized: Tribe Access Token was renewed, try again";
				} else {
					$response = "Unauthorized: Tribe Access Token was not regenrated open nginx error log";
				}
			}

			return $response;
		}

		static public function checkUserSpaces() {
			$message = MessageQ::newInstance();
			$message->sendMessage(json_encode(["function" => "joinAllSpaces", "params" => ["a" => 2, "b" => 2]]));
			$message->close();
		}

		function getReplies($id) {
			global $app;
			$arguments = [
				'postId' => $id,
				'limit' => 1000
			];
			$params = [
				'pageInfo' =>[
					'endCursor',
					'hasNextPage',
					1
				],
				'nodes' => [
					'id',
					'title',
					'slug',
					'description',
					'fields' => [
						'key',
						'value',
						1
					],
					'owner' => [
						'member' =>[
							'name',
							'profilePicture' => [
								'...on Image'=>[
									'urls'=>[
										'thumb',
									]
								]
							]
						]
					],
				]
			];
			$nodes = [];
			$tribe = new Tribe(GeneralOptions::getOption('tribe_app_token'));
			$responseObject = $tribe->getReplies($arguments, $params);
			if(isset($this->data->errors)) {
				$status = $this->handleErrors($this->data->errors);
				$this->response->setStatus($status);
				$this->respond();
			}
			$nodes[] = $responseObject->replies->nodes;
			while($responseObject->replies->pageInfo->hasNextPage){
				$responseObject = $tribe->getReplies($arguments, $params);
				$nodes[] = $responseObject->replies->nodes;
			}

			$this->data = $nodes;
			$this->result = 'success';
			$this->respond();
		}

	}