<?php

namespace Venusian\Serve\Tests\Validation;

use Mariner\Auth\Access\Gate;
use Voyager\Vessel\ControlPanel;
use Mariner\Contracts\Auth\Access\Gate as GateContract;
use Mariner\NutsAndBolts\Facades\Facade;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rules\Can;
use Mariner\Validation\ValidationServiceProvider;
use Mariner\Validation\Validator;
use stdClass;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(function () {
    $this->user = new stdClass;

    $this->container = WebApp::boot();

    $this->container->registerSingleton(GateContract::class, function () {
        return new Gate($this->container, function () {
            return $this->user;
        });
    });

    $this->container->bind('translator', function () {
        return new Translator(
            new ArrayLoader, 'en'
        );
    });

    Facade::setFacadeApplication($this->container);

    (new ValidationServiceProvider($this->container))->register();
});

afterEach(function () {
    ControlPanel::setInstance(null);

    Facade::clearResolvedInstances();

    Facade::setFacadeApplication(null);

    WebApp::tearDown($this);
});

test('validation fails', function () {
    $this->container->make(GateContract::class)->define('update-company', function ($user, $value) {
        expect($value)->toBe('1');

        return false;
    });

    $v = new Validator(
        app('translator'),
        ['company' => '1'],
        ['company' => new Can('update-company')]
    );

    expect($v->fails())->toBeTrue();
});

test('validation passes', function () {
    $this->container->make(GateContract::class)->define('update-company', function ($user, $class, $model, $value) {
        expect($class)->toEqual(\App\Models\Company::class);
        expect($model)->toBeInstanceOf(stdClass::class);
        expect($value)->toBe('1');

        return true;
    });

    $v = new Validator(
        app('translator'),
        ['company' => '1'],
        ['company' => new Can('update-company', [\App\Models\Company::class, new stdClass])]
    );

    expect($v->passes())->toBeTrue();
});

test('custom message using dot notation and fqcn works', function () {
    $v = new Validator(
        app('translator'),
        [
            'company' => '1',
            'company_fqcn' => '1',
        ],
        [
            'company' => new Can('update-company', [\App\Models\Company::class, new stdClass]),
            'company_fqcn' => new Can('update-company', [\App\Models\Company::class, new stdClass]),
        ],
        [
            'company.can' => 'You dont have permission (dot notation)',
            'company_fqcn.Mariner\Validation\Rules\Can' => 'You dont have permission (fqcn)',
        ]
    );

    expect($v->fails())->toBeTrue();

    expect($v->messages()->all())->toBe([
        'You dont have permission (dot notation)',
        'You dont have permission (fqcn)',
    ]);
});
