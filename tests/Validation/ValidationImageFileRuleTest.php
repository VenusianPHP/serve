<?php

namespace Venusian\Serve\Tests\Validation;

use Voyager\Vessel\ControlPanel;
use Voyager\Filesystem\UploadedFile;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Mariner\NutsAndBolts\Facades\Facade;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\Rules\File;
use Mariner\Validation\ValidationServiceProvider;
use Mariner\Validation\Validator;
use Venusian\Serve\Tests\Fixtures\WebApp;

test('dimensions', function () {
    imageFileRuleFails(
        File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
        UploadedFile::fake()->image('foo.png', 101, 101),
        ['validation.dimensions'],
    );

    imageFileRulePasses(
        File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
        UploadedFile::fake()->image('foo.png', 100, 100),
    );
});

test('dimensions with custom image size method', function () {
    $stream = tmpfile(); // To prevent PHP from deleting the temp file early.
    $path = stream_get_meta_data($stream)['uri'];

    imageFileRuleFails(
        File::image()->dimensions(Rule::dimensions()->width(100)->height(100)),
        new UploadedFileWithCustomImageSizeMethod($path, 'foo.png'),
        ['validation.dimensions'],
    );

    imageFileRulePasses(
        File::image()->dimensions(Rule::dimensions()->width(200)->height(200)),
        new UploadedFileWithCustomImageSizeMethod($path, 'foo.png'),
    );
});

test('dimension with the ratio method', function () {
    imageFileRuleFails(
        File::image()->dimensions(Rule::dimensions()->ratio(1)),
        UploadedFile::fake()->image('foo.png', 105, 100),
        ['validation.dimensions'],
    );

    imageFileRulePasses(
        File::image()->dimensions(Rule::dimensions()->ratio(1)),
        UploadedFile::fake()->image('foo.png', 100, 100),
    );
});

test('dimension with the min ratio method', function () {
    imageFileRulePasses(
        File::image()->dimensions(Rule::dimensions()->minRatio(1 / 2)),
        UploadedFile::fake()->image('foo.png', 100, 100),
    );

    imageFileRulePasses(
        File::image()->dimensions(Rule::dimensions()->minRatio(2 / 3)),
        UploadedFile::fake()->image('foo.png', 200, 300),
    );

    imageFileRuleFails(
        File::image()->dimensions(Rule::dimensions()->minRatio(1 / 2)),
        UploadedFile::fake()->image('foo.png', 100, 300),
        ['validation.dimensions'],
    );
});

test('dimension with the max ratio method', function () {
    imageFileRulePasses(
        File::image()->dimensions(Rule::dimensions()->maxRatio(1 / 2)),
        UploadedFile::fake()->image('foo.png', 100, 300),
        ['validation.dimensions'],
    );

    imageFileRulePasses(
        File::image()->dimensions(Rule::dimensions()->maxRatio(1 / 3)),
        UploadedFile::fake()->image('foo.png', 100, 300),
    );

    imageFileRuleFails(
        File::image()->dimensions(Rule::dimensions()->maxRatio(1 / 2)),
        UploadedFile::fake()->image('foo.png', 100, 100),
        ['validation.dimensions'],
    );
});

test('dimension with the ratio between method', function () {
    imageFileRuleFails(
        File::image()->dimensions(Rule::dimensions()->ratioBetween(1 / 3, 1 / 2)),
        UploadedFile::fake()->image('foo.png', 100, 100),
        ['validation.dimensions'],
    );

    imageFileRulePasses(
        File::image()->dimensions(Rule::dimensions()->ratioBetween(1 / 3, 1 / 2)),
        UploadedFile::fake()->image('foo.png', 100, 200),
    );
});

function imageFileRuleFails($rule, $values, $messages)
{
    imageFileRuleAssertValidationRules($rule, $values, false, $messages);
}

function imageFileRuleAssertValidationRules($rule, $values, $result, $messages)
{
    $values = Arr::wrap($values);

    foreach ($values as $value) {
        $v = new Validator(
            app('translator'),
            ['my_file' => $value],
            ['my_file' => is_object($rule) ? clone $rule : $rule]
        );

        expect($v->passes())->toBe($result);

        expect($v->messages()->toArray())->toBe($result ? [] : ['my_file' => $messages]);
    }
}

function imageFileRulePasses($rule, $values)
{
    imageFileRuleAssertValidationRules($rule, $values, true, []);
}

beforeEach(function () {
    $container = WebApp::boot();

    $container->bind('translator', function () {
        return new Translator(
            new ArrayLoader, 'en'
        );
    });

    Facade::setFacadeApplication($container);

    (new ValidationServiceProvider($container))->register();
});

afterEach(function () {
    ControlPanel::setInstance(null);

    Facade::clearResolvedInstances();

    Facade::setFacadeApplication(null);

    WebApp::tearDown($this);
});

class UploadedFileWithCustomImageSizeMethod extends UploadedFile
{
    public function isValid(): bool
    {
        return true;
    }

    public function guessExtension(): string
    {
        return 'png';
    }

    public function dimensions()
    {
        return [200, 200];
    }
}
