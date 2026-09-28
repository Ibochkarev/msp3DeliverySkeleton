<?php

/**
 * System settings for msp3DeliverySkeleton.
 * Key in MODX: msp3deliveryskeleton_<key>
 */

return [
    'api_url' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'msp3deliveryskeleton',
        'name' => 'setting_msp3deliveryskeleton_api_url',
        'description' => 'setting_msp3deliveryskeleton_api_url_desc',
    ],
    'api_key' => [
        'xtype' => 'text-password',
        'value' => '',
        'area' => 'msp3deliveryskeleton',
        'name' => 'setting_msp3deliveryskeleton_api_key',
        'description' => 'setting_msp3deliveryskeleton_api_key_desc',
    ],
    'account' => [
        'xtype' => 'textfield',
        'value' => '',
        'area' => 'msp3deliveryskeleton',
        'name' => 'setting_msp3deliveryskeleton_account',
        'description' => 'setting_msp3deliveryskeleton_account_desc',
    ],
    'secret' => [
        'xtype' => 'text-password',
        'value' => '',
        'area' => 'msp3deliveryskeleton',
        'name' => 'setting_msp3deliveryskeleton_secret',
        'description' => 'setting_msp3deliveryskeleton_secret_desc',
    ],
    'test_mode' => [
        'xtype' => 'combo-boolean',
        'value' => true,
        'area' => 'msp3deliveryskeleton',
        'name' => 'setting_msp3deliveryskeleton_test_mode',
        'description' => 'setting_msp3deliveryskeleton_test_mode_desc',
    ],
    'timeout' => [
        'xtype' => 'numberfield',
        'value' => 10,
        'area' => 'msp3deliveryskeleton',
        'name' => 'setting_msp3deliveryskeleton_timeout',
        'description' => 'setting_msp3deliveryskeleton_timeout_desc',
    ],
    'debug' => [
        'xtype' => 'combo-boolean',
        'value' => false,
        'area' => 'msp3deliveryskeleton',
        'name' => 'setting_msp3deliveryskeleton_debug',
        'description' => 'setting_msp3deliveryskeleton_debug_desc',
    ],
];
