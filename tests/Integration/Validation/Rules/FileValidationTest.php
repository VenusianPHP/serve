<?php

namespace Venusian\Serve\Tests\Integration\Validation\Rules;

use Voyager\Filesystem\UploadedFile;
use Mariner\NutsAndBolts\Facades\Validator;
use Mariner\Validation\Rule;
use Mariner\Validation\Rules\File;

test('it can validate attribute as array', function (string $attribute) {
    $file = UploadedFile::fake()->create('laravel.png', 1, 'image/png');

    $validator = Validator::make([
        'files' => [
            $attribute => $file,
        ],
    ], [
        'files.*' => ['required', File::types(['image/png', 'image/jpeg'])],
    ]);

    expect($validator->passes())->toBeTrue();
})->with([
    ['0'],
    ['.'],
    ['*'],
    ['__asterisk__'],
]);

test('it can validate attribute as array when validation should fails', function (string $attribute) {
    $file = UploadedFile::fake()->create('laravel.php', 1, 'image/php');

    $validator = Validator::make([
        'files' => [
            $attribute => $file,
        ],
    ], [
        'files.*' => ['required', File::types($mimes = ['image/png', 'image/jpeg'])],
    ]);

    expect($validator->passes())->toBeFalse();

    expect($validator->messages()->all())->toBe([
        0 => __('validation.mimetypes', ['attribute' => sprintf('files.%s', str_replace('_', ' ', $attribute)), 'values' => implode(', ', $mimes)]),
    ]);
})->with([
    ['0'],
    ['.'],
    ['*'],
    ['__asterisk__'],
]);

test('file custom validation messages', function () {
    $validator = Validator::make(
        [
            'one' => UploadedFile::fake()->create('photo', 1000),
            'two' => 'not-a-file',
        ],
        [
            'one' => [File::default()->max(50)],
            'two' => [File::default()->max(50)],
        ],
        [
            'one.max' => 'File one is too large',
            'one.file' => 'File one is not a file',
            'two.max' => 'File two is too large',
            'two.file' => 'File two is not a file',
        ]);

    expect($validator->fails())->toBeTrue();

    expect($validator->messages()->all())->toBe([
        'File one is too large',
        'File two is not a file',
    ]);
});

test('file mimes custom validation messages', function () {
    $validator = Validator::make(
        ['document' => UploadedFile::fake()->create('file.pdf')],
        ['document' => [File::types(['jpg', 'png'])]],
        ['document.mimes' => 'Wrong file type']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['Wrong file type']);
});

test('file min size custom validation messages', function () {
    $validator = Validator::make(
        ['upload' => UploadedFile::fake()->create('small.pdf', 50)],
        ['upload' => [File::types(['pdf'])->min(100)]],
        ['upload.min' => 'File too small']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['File too small']);
});

test('file max size custom validation messages', function () {
    $validator = Validator::make(
        ['upload' => UploadedFile::fake()->create('large.pdf', 2000)],
        ['upload' => [File::types(['pdf'])->max(1024)]],
        ['upload.max' => 'File exceeds limit']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['File exceeds limit']);
});

test('file dimension custom validation messages', function () {
    $validator = Validator::make(
        ['image' => UploadedFile::fake()->image('foo.jpg', 100, 100)],
        ['image' => [File::image()->dimensions(Rule::dimensions()->width(50)->height(50))]],
        ['image.dimensions' => 'Invalid dimensions']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['Invalid dimensions']);
});

test('file between custom validation messages', function () {
    $validator = Validator::make(
        ['file' => UploadedFile::fake()->create('foo.pdf', 10)],
        ['file' => [File::types(['pdf'])->between(100, 1000)]],
        ['file.between' => 'Size out of range']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['Size out of range']);
});

test('image custom validation messages', function () {
    $validator = Validator::make(
        ['avatar' => UploadedFile::fake()->create('foo.txt')],
        ['avatar' => [File::image()]],
        ['avatar.image' => 'Not an image']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['Not an image']);
});

test('file multiple custom validation messages', function () {
    $validator = Validator::make(
        ['photo' => UploadedFile::fake()->create('foo.pdf', 5000)],
        ['photo' => [File::types(['jpg', 'png'])->max(1024)]],
        [
            'photo.mimes' => 'Invalid type',
            'photo.max' => 'Too large',
        ]
    );

    expect($validator->fails())->toBeTrue();

    $messages = $validator->messages()->all();

    expect($messages)->toContain('Invalid type');
    expect($messages)->toContain('Too large');
});

test('file size custom validation messages', function () {
    $validator = Validator::make(
        ['file' => UploadedFile::fake()->create('doc.pdf', 500)],
        ['file' => [File::types(['pdf'])->size(100)]],
        ['file.size' => 'File must be exactly 100KB']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['File must be exactly 100KB']);
});

test('file extensions custom validation messages', function () {
    $validator = Validator::make(
        ['file' => UploadedFile::fake()->create('foo.pdf')],
        ['file' => [File::default()->extensions(['txt', 'doc'])]],
        ['file.extensions' => 'Invalid file extension']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['Invalid file extension']);
});

test('file encoding custom validation messages', function () {
    $validator = Validator::make(
        ['file' => UploadedFile::fake()->createWithContent('foo.txt', "\xf0\x28\x8c\x28")],
        ['file' => [File::types(['txt'])->encoding('UTF-8')]],
        ['file.encoding' => 'Invalid file encoding']
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->messages()->all())->toBe(['Invalid file encoding']);
});
