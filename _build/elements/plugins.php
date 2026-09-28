<?php

return [
    'msp3deliveryskeleton' => [
        'file' => 'msp3deliveryskeleton',
        'description' => 'Loads msp3DeliverySkeleton autoload',
        'events' => [
            'OnMODXInit' => ['priority' => 0],
            // INIT:manager:begin
            'msOnManagerCustomCssJs' => ['priority' => 0],
            // INIT:manager:end
        ],
    ],
];
