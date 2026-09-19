<?php

return [
    'currency' => [
        'code' => 'AFN',
        'symbol' => '؋',
        'precision' => 2,
    ],

    'tax_enabled' => false,

    'locales' => [
        'en' => ['label' => 'English', 'direction' => 'ltr'],
        'fa' => ['label' => 'دری', 'direction' => 'rtl'],
        'ps' => ['label' => 'پښتو', 'direction' => 'rtl'],
    ],

    'default_receipt_size' => '80mm',
    'supported_receipt_sizes' => ['58mm', '80mm'],
];
