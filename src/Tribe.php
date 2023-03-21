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
     * @param $query
     * @return mixed
     */
    public function getMembers($query = ''){
        $arguments = [
            "limit" => 1000,
            "query" => $query
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
        if(isset($response->data)) return $response->data;
        return $response;
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
                if(is_int($field)){
                    for($i= 1; $i <= $field; $i++){
                        $nodes = $nodes->prev();
                    }
                    continue;
                }
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
     * @param $fields
     * @param $variables
     * @param $params
     * @param $instance
     * @return mixed
     */
    private function createInstanceNameArray($name, $fields = [], $variables = [], $params = [], $instance) {
        $inputs = [];
        foreach ($variables as $key => $value) {
            $inputs[] = new Variable('input', $instance . 'Input!', '');
        }
        $fields['input'] = new Variable('input', "[".$instance . 'Input!'."]!", '');
        $mutation = new Mutation($name, $fields);
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

    public function getTemplateRequest($arguments, $params){
        $instance = new Graph('templateRequest', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        return $this->request($query);
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
                'publish' => true,
                'mappingFields' => [
                    $this->mappingField('title', 'text', $title),
                    $this->mappingField('content', 'html', $content)
                ]
            ]
        ];

        return $this->createInstance('createPost', $fields, $variables, $params);
    }

    public function createPostFromMember($space_id, $title, $content, $params = [], $member_id, $fields = []) {

        $fields = array_merge(['spaceId' => $space_id], $fields);

        $variables = [
            'input' => [
                'postTypeId' => 'udE3pz9DBGv7nsr',
                'publish' => true,
                'ownerId' => $member_id,
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
    public function createReply($post_id, $content, $params = [], $member_id) {
        $fields = ['postId' => $post_id];

        $variables = [
            'input' => [
                'postTypeId' => 'fu1HHZaXZzs82C5',
                'publish' => true,
                'ownerId' => $member_id,
                'mappingFields' => [
                    $this->mappingField('content', 'html', $content)
                ]
            ]
        ];

        return $this->createInstanceName('createReply', $fields, $variables, $params, "CreatePost");
    }

    /**
     * @param $post_id
     * @param $content
     * @param $params
     * @param $fields
     * @return mixed
     */
    public function createReplyV2($post_id, $content, $params = [], $fields = []) {

        $fields = array_merge(['postId' => $post_id], $fields);

        $variables = [
            'input' => [
                'postTypeId' => 'fu1HHZaXZzs82C5',
                'publish' => true,
                'mappingFields' => [
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
        $fields['templateId'] = "pYCdmKZ3KZT8";
        $response =  $this->createInstanceName('createSpaceFromTemplate', $fields, $variables, $params, 'CreateSpace');
        return $response;
    }

    public function updatePostType($variables = [], $space_id) {
        /*  Params lo que quieres saber */
        /*  Fields parametros de un solo nivel*/
        /*  Variables parametros de varios niveles */
        $variables = [
            'input' => $variables
        ];
        $fields['spaceId'] = $space_id;
        $params = [
            'spaceId'
        ];
        return $this->createInstanceNameArray('updateSpacePostTypes', $fields, $variables, $params, 'UpdateSpacePostType');
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
     * @return mixed
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
        if(!isset($image_api_object)) {
            return  $response->data;
        }
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
        $response = $this->createInstance('updateMember', $fields, $variables, $params);
        if(isset($response->errors)) return $response;
        return $response;
    }

    /**
     * @param $arguments
     * @param $params
     * @return mixed
     */
    public function getNotifications($arguments, $params) {

        $instance = new Graph('getNotifications', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        if(isset($response->data->getNotifications->nodes)){
            $response = $response->data->getNotifications->nodes;
        }else{
            $response = $response->data;
        }
        return $response;
    }

    /**
     * @param $params
     * @return mixed
     */
    public function getNotificationsCount($params) {

        $instance = new Graph('getNotificationsCount');
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        if(isset($response->errors)) return $response;
        return $response->data;
    }

    /**
     * @param $arguments
     * @return mixed
     */
    public function readNotification($arguments){
        $mutation = new Mutation('readNotification');
        $mutation->readNotification($arguments)->use('status')->root()->query();
        $query = $mutation;
        $query = $query->root()->query();
        $response = $this->request($query);
        if(isset($response->errors)) return $response;
        return $response->data->readNotification;
    }

    /**
     * @param $arguments
     * @param $params
     * @return mixed
     */
    public function getReplies($arguments, $params){
        $instance = new Graph('replies', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $query = str_replace('"updatedAt"',"updatedAt", $query);
        $response = $this->request($query);

        if(isset($response->errors)) return $response;
        return $response->data;
    }

    /**
     * @param $arguments
     * @param $params
     * @param $variables
     * @return mixed
     */
    public function addReaction($arguments, $params, $variables){
        return $this->createInstance('addReaction', $arguments, $variables, $params);
    }

    /**
     * @param $arguments
     * @return mixed
     */
    public function removeReaction($arguments){
        $mutation = new Mutation('removeReaction');

        $mutation->removeReaction($arguments)->use('status')->root()->query();

        $query = $mutation;

        $query = $query->root()->query();

        $response = $this->request($query);

        return $response->data->removeReaction;

    }

    /**
     * @param $query
     * @return mixed
     */
    public function rawQuery($query){
        $response = $this->request($query);
        if(isset($response->errors)) return $response;
        return $response->data;
    }

    /**
     * @param $arguments
     * @param $params
     * @return mixed
     */
    public function getFeed($arguments, $params){
        $instance = new Graph('feed', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);

        if(isset($response->errors)) return $response;
        return $response->data->feed;
    }

    /**
     * @param $arguments
     * @return mixed
     */
    public function joinSpaceUser($arguments){
        $mutation = new Mutation('joinSpace');

        $mutation->joinSpace($arguments)->use('status')->root()->query();

        $query = $mutation;

        $query = $query->root()->query();

        $response = $this->request($query);

        if(isset($response->errors)) return $response;
        if(isset($response->data)){
            return $response->data;
        }
        return $response->data;

    }

    /**
     * @param $arguments
     * @return mixed
     */
    public function leaveSpace($arguments){
        $mutation = new Mutation('leaveSpace');

        $mutation->leaveSpace($arguments)->use('status')->root()->query();

        $query = $mutation;

        $query = $query->root()->query();

        $response = $this->request($query);

        if(isset($response->errors)) return $response;

        return $response->data;

    }

    /**
     * @param $space_id
     * @return mixed
     */
    public function removeAllMembers($space_id){
        $memberIds = [];
        $arguments = [
            "spaceId" => $space_id,
            "limit"=> 1000
        ];
        $params = [
            "pageInfo" => [
                "endCursor",
                "hasNextPage",
                1
            ],
            "nodes" => [
                "member" => [
                    "id"
                ]
            ]
        ];
        $instance = new Graph('spaceMembers', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        if(isset($response->errors)) return $response;
        $spaceMembers = $response->data->spaceMembers;
        foreach($spaceMembers->nodes as $node){
            $memberIds[] = $node->member->id;
        }
        while($spaceMembers->pageInfo->hasNextPage){
            $arguments = [
                "limit"=> 1000,
                "after" => $spaceMembers->pageInfo->endCursor,
                "spaceId" => $space_id,
            ];

            $instance = new Graph('spaceMembers', $arguments);
            $query = $this->generateNodeFields($instance, $params);
            $query = $instance->root()->query();
            $response = $this->request($query);
            foreach($response->nodes as $node){
                $memberIds[] = $node->member->id;
            }
        }

        $mutation = new Mutation('removeSpaceMembers');
        $arguments = [
            "memberIds" => $memberIds,
            "spaceId" => $space_id
        ];
        $mutation->removeSpaceMembers($arguments)->use('status')->root()->query();

        $query = $mutation;

        $query = $query->root()->query();

        $response = $this->request($query);

        if(isset($response->errors)) return $response;

        return $response->data;

    }

    /**
     * @param $memberId
     * @param $params
     * @return mixed
     */
    public function getMemberSpaces($memberId, $params){
        $arguments = [
            "memberId" => $memberId,
            "limit" => 1
        ];
        $instance = new Graph('memberSpaces', $arguments);
        $query = $this->generateNodeFields($instance, $params);
        $query = $instance->root()->query();
        $response = $this->request($query);
        if(isset($response->errors)) return $response;
        return $response->data;

    }

    /**
     * @param $members
     * @param $id
     * @return mixed
     */
    public function addSpaceMembers($members, $id){
        $params = [
            "member" => [
                "id",
                "name"
            ]
        ];
        $variables['input'] = $members;
        $variables['spaceId'] = $id;
        $fields = ["spaceId" =>  $id];
        return $this->createInstanceNameArray('addSpaceMembers', $fields, $variables, $params, "AddSpaceMember");

    }

    public function removeSpaceMembers($members, $id){
        $arguments = ["memberIds" =>  $members, "spaceId" =>  $id];
        $mutation = new Mutation('removeSpaceMembers');
        $mutation->removeSpaceMembers($arguments)->use('status')->root()->query();
        $query = $mutation;
        $query = $query->root()->query();
        $response = $this->request($query);
        if(isset($response->errors)) return $response;
        return $response->data;
    }
}
?>
