<?php

return [
    'min_php_version' => '8.2.0',

    'requirements' => [
        'php' => [
            'openssl',
            'pdo',
            'mbstring',
            'tokenizer',
            'JSON',
            'cURL',
        ],
        'apache' => [
            'mod_rewrite',
        ],
    ],

    'permissions' => [
        'storage/framework/' => '775',
        'storage/logs/' => '775',
        'bootstrap/cache/' => '775',
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Form Fields (Dynamic)
    |--------------------------------------------------------------------------
    | Supported Types:
    | - text
    | - email
    | - textarea
    | - password
    | - select
    | - multiselect
    | - multisearch
    */
    'account' => [
        [
            'type' => 'text',
            'key' => 'name',
            'label' => 'Full Name',
            'required' => true,
            'rules' => 'required|min:3|max:50',
        ],

        [
            'type' => 'email',
            'key' => 'email',
            'label' => 'Email Address',
            'required' => true,
            'rules' => 'required|email',
        ],

        [
            'type' => 'password',
            'key' => 'password',
            'label' => 'Password',
            'required' => true,
            'rules' => 'required|min:6',
        ],

        [
            'type' => 'textarea',
            'key' => 'bio',
            'label' => 'Short Bio',
            'required' => false,
            'rules' => 'nullable|max:200',
        ],

        [
            'type' => 'select',
            'key' => 'role',
            'label' => 'Select Role',
            'options' => ['Admin', 'Editor', 'Viewer'],
            'required' => true,
            'rules' => 'required',
        ],

        [
            'type' => 'multiselect',
            'key' => 'modules',
            'label' => 'Allowed Modules',
            'options' => ['orders', 'products', 'invoices', 'subscriptions'],
            'required' => false,
            'rules' => 'nullable',
        ],

        [
            'type' => 'multisearch',
            'key' => 'tags',
            'label' => 'Search & Add Tags',
            'options' => ['php', 'laravel', 'vue', 'react', 'sql', 'linux'],
            'required' => false,
            'rules' => 'nullable',
        ],
    ],
];
