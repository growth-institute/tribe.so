<?php

namespace TribePHP;

use Curl\Curl;
use GraphQL\Graph;
use GraphQL\Mutation;
use GraphQL\Variable;


class Tribe {

    /**
     * @var
     */
    private $api_key;
    /**
     * @var
     */
    private $community_id;


    /**
     * @param $api_key
     */
    public function __construct($api_key) {
        $this->api_key = $api_key;
        $this->baseUrl = 'https://app.tribe.so/graphql';
    }

    /**
     * @param $networkId
     * @param $url
     * @param $memberId
     * @return mixed
     */
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

    /**
     * @param $user_access_token
     * @param $space_id
     * @return mixed
     */
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

    /**
     * @return mixed
     */
    public function getMembers(){
        $arguments = [
            "limit" => 1000
        ];
        $params = [
            "totalCount",
            "nodes" => [
                "id",
                "name",
                "email",
                "username",
                "profilePictureId"
            ]
        ];
        $instance = new Graph('members', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        return $response->data;
    }

    /**
     * @param $query
     * @param $variables
     * @return mixed
     */
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

    /**
     * @param $nodes
     * @param $fields
     * @return mixed
     */
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

    /**
     * @param $name
     * @param $fields
     * @param $params
     * @param $defaults
     * @return mixed
     */
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

    /**
     * @param $name
     * @param $fields
     * @param $variables
     * @param $params
     * @return mixed
     */
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

    /**
     * @param $name
     * @param $fields
     * @param $variables
     * @param $params
     * @param $instance
     * @return mixed
     */
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

    /**
     * @param $name
     * @param $type
     * @param $value
     * @return array
     */
    private function mappingField($name, $type, $value) {

        if(in_array($type, ['text', 'html'])) $value = "\"{$value}\"";

        return [
            'key' => $name,
            'type' => $type,
            'value' => $value
        ];
    }

    /**
     * @param $arguments
     * @param $params
     * @return mixed
     */
    public function getPosts($arguments, $params) {

        $instance = new Graph('posts', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        return $response->data->posts;
    }

    /**
     * @param $arguments
     * @param $params
     * @return mixed
     */
    public function getSpace($arguments, $params){
        $instance = new Graph('space', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        return $response;
    }

    /**
     * @param $arguments
     * @param $params
     * @return mixed
     */
    public function getPost($arguments, $params){
        $instance = new Graph('post', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        return $response->data;
    }

    /**
     * @param $post_id
     * @param $content
     * @param $params
     * @return mixed
     */
    public function updatePost($post_id, $content, $params){
        $fields = ['id' => $post_id];

        $variables = [
            'input' => [
                'publish' => true,
                'mappingFields' => [
                    $this->mappingField('title', 'text', ""),
                    $this->mappingField('content', 'html', $content),
                ]
            ]
        ];

        return $this->createInstance('updatePost', $fields, $variables, $params);
    }

    /**
     * @param $space_id
     * @param $title
     * @param $content
     * @param $params
     * @param $fields
     * @return mixed
     */
    public function createPost($space_id, $title, $content, $params = [], $fields = []) {

        $fields = array_merge(['spaceId' => $space_id], $fields);

        $variables = [
            'input' => [
                'postTypeId' => 'udE3pz9DBGv7nsr',
                'publish' => false,
                'mappingFields' => [
                    $this->mappingField('title', 'text', $title),
                    $this->mappingField('content', 'html', $content)
                ]
            ]
        ];

        return $this->createInstance('createPost', $fields, $variables, $params);
    }

    /**
     * @param $arguments
     * @param $params
     * @return mixed
     */
    public function getSpaces($arguments, $params){
        $instance = new Graph('spaces', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        return $response->data;
    }

    /**
     * @param $params
     * @return mixed
     */
    public function getCollections($params){
        $instance = new Graph('collections');
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        return $response;
    }

    /**
     * @param $params
     * @param $variables
     * @param $input
     * @return mixed
     */
    public function updateSpace($params, $variables, $input) {

        $input = [
            'input' => $input
        ];

        return $this->createInstance('updateSpace', $variables, $input, $params);
    }

    /**
     * @param $post_id
     * @param $content
     * @param $params
     * @param $fields
     * @return mixed
     */
    public function createReply($post_id, $content, $params = [], $fields = []) {

        $fields = array_merge(['postId' => $post_id], $fields);

        $variables = [
            'input' => [
                'postTypeId' => 'udE3pz9DBGv7nsr',
                'publish' => true,
                'mappingFields' => [
                    $this->mappingField('title', 'text', ''),
                    $this->mappingField('content', 'html', $content)
                ]
            ]
        ];

        return $this->createInstanceName('createReply', $fields, $variables, $params, "CreatePost");
    }

    /**
     * @param $params
     * @param $variables
     * @param $fields
     * @return mixed
     */
    public function createSpace($params = [], $variables = [], $fields = []) {
        /*  Params lo que quieres saber */
        /*  Fields parametros de un solo nivel*/
        /*  Variables parametros de varios niveles */
        $variables = [
            'input' => $variables
        ];
        return $this->createInstance('createSpace', $fields, $variables, $params);
    }

    /**
     * @param $params
     * @param $variables
     * @param $fields
     * @return mixed
     */
    public function deleteSpace($params = [], $variables = [], $fields = []){

        $params = [
            "status"
        ];

        $mutation = new Mutation('deleteSpace');

        $query = $mutation->deleteSpace($variables)->use('status')->root()->query();
        $response = $this->request($query);
        return $response;
    }

    /**
     * @param $variables
     * @param $params
     * @param $fields
     * @return string
     */
    public function createImages($variables = [], $params = [], $fields = []) {

        $variables = [
            'input' => $variables
        ];
        $mutation = new Mutation('createImages', [ 'input'=> new Variable('input', '[CreateImageInput!]!', '') ]);

        $query = $this->generateNodeFields($mutation, $params);

        $query = $query->root()->query();

        $response = $this->request($query, $variables);
        $image_api_object =  $response->data->createImages[0];
        // Validate response
        if(!isset($image_api_object)) return "Error, image signedUrl creation failed";

        return $image_api_object;

    }

    /**
     * @param $variables
     * @param $params
     * @param $fields
     * @return mixed
     */
    public function updateMember($variables = [], $params = [], $fields = []){
        $variables = [
            'input' => $variables
        ];
        return $this->createInstance('updateMember', $fields, $variables, $params);
    }
}
?>