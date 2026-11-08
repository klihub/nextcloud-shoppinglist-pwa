<?php
declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'shopping_list#index', 'url' => '/api/lists', 'verb' => 'GET'],
        ['name' => 'shopping_list#create', 'url' => '/api/lists', 'verb' => 'POST'],
        ['name' => 'shopping_list#show', 'url' => '/api/lists/{id}', 'verb' => 'GET'],
        ['name' => 'shopping_list#destroy', 'url' => '/api/lists/{id}', 'verb' => 'DELETE'],
        ['name' => 'shopping_list#members', 'url' => '/api/lists/{id}/members', 'verb' => 'GET'],
        ['name' => 'shopping_list#addMember', 'url' => '/api/lists/{id}/members', 'verb' => 'POST'],
        ['name' => 'shopping_list#removeMember', 'url' => '/api/lists/{id}/members/{userId}', 'verb' => 'DELETE'],
        ['name' => 'shopping_list#items', 'url' => '/api/lists/{id}/items', 'verb' => 'GET'],
        ['name' => 'shopping_list#createItem', 'url' => '/api/lists/{id}/items', 'verb' => 'POST'],
        ['name' => 'shopping_list#updateItem', 'url' => '/api/lists/{id}/items/{itemId}', 'verb' => 'PUT'],
        ['name' => 'shopping_list#deleteItem', 'url' => '/api/lists/{id}/items/{itemId}', 'verb' => 'DELETE'],
    ],
];
