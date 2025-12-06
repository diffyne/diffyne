<?php

use Diffyne\Attributes\Invokable;
use Diffyne\Attributes\Locked;
use Diffyne\Component;
use Illuminate\View\View;

test('component can be instantiated', function () {
    $component = new class extends Component
    {
        public string $name = 'Test';

        public function render(): View
        {
            return view($this->view());
        }
    };

    expect($component)->toBeInstanceOf(Component::class)
        ->and($component->name)->toBe('Test')
        ->and($component->id)->toBeString();
});

test('component properties can be set and retrieved', function () {
    $component = new class extends Component
    {
        public string $title = 'Default Title';
        public int $count = 0;

        public function render(): View
        {
            return view($this->view());
        }
    };

    $component->title = 'New Title';
    $component->count = 42;

    expect($component->title)->toBe('New Title')
        ->and($component->count)->toBe(42);
});

test('component can have invokable methods', function () {
    $component = new class extends Component
    {
        public int $counter = 0;

        #[Invokable]
        public function increment(): void
        {
            $this->counter++;
        }

        public function render(): View
        {
            return view($this->view());
        }
    };

    expect($component->counter)->toBe(0);

    $component->increment();

    expect($component->counter)->toBe(1);
});

test('component can have locked properties', function () {
    $component = new class extends Component
    {
        #[Locked]
        public string $secret = 'protected';

        public string $public = 'accessible';

        public function render(): View
        {
            return view($this->view());
        }
    };

    expect($component->secret)->toBe('protected')
        ->and($component->public)->toBe('accessible');
});

test('component generates unique id', function () {
    $component1 = new class extends Component
    {
        public function render(): View
        {
            return view($this->view());
        }
    };

    $component2 = new class extends Component
    {
        public function render(): View
        {
            return view($this->view());
        }
    };

    expect($component1->id)->not->toBe($component2->id)
        ->and($component1->id)->toBeString()
        ->and($component2->id)->toBeString();
});

