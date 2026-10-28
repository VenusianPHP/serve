<?php

use Mariner\Http\Request;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Filesystem\UploadedFile;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->root = sys_get_temp_dir().'/mariner-uploads-'.bin2hex(random_bytes(4));
    $this->app['config']->set('filesystems.disks.uploads', ['driver' => 'local', 'root' => $this->root]);
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->root);
    WebApp::tearDown($this);
});

it('hands out Venusian uploaded files and stores them on a disk', function () {
    $request = Request::create('/avatar', 'POST', files: ['avatar' => UploadedFile::fake()->create('me.txt', 1)]);

    $file = $request->file('avatar');
    $path = $file->storeAs('avatars', 'me.txt', 'uploads');

    expect($file)->toBeInstanceOf(UploadedFile::class)
        ->and($path)->toBe('avatars/me.txt')
        ->and(is_file($this->root.'/avatars/me.txt'))->toBeTrue();
});
