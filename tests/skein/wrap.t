#!/usr/bin/env php
<?php
use Gaia\Skein;
use Gaia\Container;
use Gaia\Test\Tap;

include __DIR__ . '/../common.php';
include __DiR__ . '/../assert/date_configured.php';

$skein = new Skein\Wrap( new Skein\Store( $store = new Container() ) );

include __DIR__ . '/.basic_test_suite.php';


//Tap::debug( $store->all() );