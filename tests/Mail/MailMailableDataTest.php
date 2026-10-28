<?php

namespace Venusian\Serve\Tests\Mail;

use Mariner\Mail\Mailable;

test('mailable data is not lost', function () {
    $mailable = new MailableStub;

    $testData = [
        'first_name' => 'James',
        '__mariner_mailable' => get_class($mailable),
    ];

    $mailable->build(function ($m) use ($testData) {
        $m->view('view', $testData);
    });
    expect($mailable->buildViewData())->toBe($testData);

    $mailable = new MailableStub;
    $mailable->build(function ($m) use ($testData) {
        $m->view('view', $testData)
            ->text('text-view');
    });
    expect($mailable->buildViewData())->toBe($testData);
});

class MailableStub extends Mailable
{
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build($builder)
    {
        $builder($this);
    }
}
