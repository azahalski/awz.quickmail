<?php
return [
    'controllers' => [
        'value' => [
            'namespaces' => [
                '\\Awz\\Quickmail\\Controller' => 'api'
            ]
        ],
        'readonly' => true
    ],
    'ui.entity-selector' => [
        'value' => [
            'entities' => [
                [
                    'entityId' => 'awzquickmail-user',
                    'provider' => [
                        'moduleId' => 'awz.quickmail',
                        'className' => '\\Awz\\Quickmail\\Access\\EntitySelectors\\User'
                    ],
                ],
                [
                    'entityId' => 'awzquickmail-group',
                    'provider' => [
                        'moduleId' => 'awz.quickmail',
                        'className' => '\\Awz\\Quickmail\\Access\\EntitySelectors\\Group'
                    ],
                ],
            ]
        ],
        'readonly' => true,
    ]
];