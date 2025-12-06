<?php

use Diffyne\DiffyneManager;
use Diffyne\VirtualDOM\Renderer;
use Diffyne\State\ComponentHydrator;

test('service provider registers renderer', function () {
    // The service provider should register the Renderer
    // With orchestra/pest-plugin-testbench, app() helper works directly
    expect(app()->bound(Renderer::class))->toBeTrue();
    
    $renderer = app(Renderer::class);
    expect($renderer)->toBeInstanceOf(Renderer::class);
});

test('service provider registers component hydrator', function () {
    // The service provider should register the ComponentHydrator
    expect(app()->bound(ComponentHydrator::class))->toBeTrue();
    
    $hydrator = app(ComponentHydrator::class);
    expect($hydrator)->toBeInstanceOf(ComponentHydrator::class);
});

test('diffyne manager is registered', function () {
    // The service provider should register the 'diffyne' binding
    expect(app()->bound('diffyne'))->toBeTrue();
    
    $manager = app('diffyne');
    expect($manager)->toBeInstanceOf(DiffyneManager::class);
});

