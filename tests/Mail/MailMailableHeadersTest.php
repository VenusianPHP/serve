<?php

namespace Venusian\Serve\Tests\Mail;

use Mariner\Mail\Mailables\Headers;

test('', function () {
    $headers = new Headers(
        '434571BC.8070702@example.net',
        [
            '<19980506192030.26456.qmail@cr.yp.to>',
            '<19980507220459.5655.qmail@warren.demon.co.uk>',
            '<19980508103652.B21462@iconnect.co.ke>',
            '<19980509035615.40087@rucus.ru.ac.za>',
        ],
    );

    expect($headers->referencesString())->toBe('<19980506192030.26456.qmail@cr.yp.to> <19980507220459.5655.qmail@warren.demon.co.uk> <19980508103652.B21462@iconnect.co.ke> <19980509035615.40087@rucus.ru.ac.za>');
});
