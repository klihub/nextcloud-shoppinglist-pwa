<?php
declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'shopping_list#index', 'url' => '/api/lists', 'verb' => 'GET'],
    ],
];
