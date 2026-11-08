<?php
declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'shopping_list#index', 'url' => '/api/lists', 'verb' => 'GET'],
        ['name' => 'shopping_list#create', 'url' => '/api/lists', 'verb' => 'POST'],
        ['name' => 'shopping_list#show', 'url' => '/api/lists/{id}', 'verb' => 'GET'],
        ['name' => 'shopping_list#destroy', 'url' => '/api/lists/{id}', 'verb' => 'DELETE'],
    ],
];
