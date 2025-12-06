<?php

use Diffyne\Attributes\Invokable;
use Diffyne\Attributes\Locked;

test('invokable attribute can be applied to methods', function () {
    $reflection = new ReflectionClass(new class
    {
        #[Invokable]
        public function testMethod(): void
        {
        }
    });

    $method = $reflection->getMethod('testMethod');
    $attributes = $method->getAttributes(Invokable::class);

    expect($attributes)->not->toBeEmpty()
        ->and($attributes[0]->getName())->toBe(Invokable::class);
});

test('locked attribute can be applied to properties', function () {
    $reflection = new ReflectionClass(new class
    {
        #[Locked]
        public string $lockedProperty = 'value';
    });

    $property = $reflection->getProperty('lockedProperty');
    $attributes = $property->getAttributes(Locked::class);

    expect($attributes)->not->toBeEmpty()
        ->and($attributes[0]->getName())->toBe(Locked::class);
});

