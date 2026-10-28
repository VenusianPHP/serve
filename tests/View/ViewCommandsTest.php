<?php

namespace Venusian\Serve\Tests\View;

use Mariner\Core\Testing\TestCase;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Core\RenderedInstance;
use Voyager\Filesystem\Filesystem;

/** view:cache and view:clear on a copy of the fixture app. */
class ViewCommandsTest extends TestCase
{
    private string $root;

    public function createApplication(): RenderedInstance
    {
        $app = WebApp::bootFrom($this->root = WebApp::copy());
        $app['config']->set('view.compiled', $this->root.'/storage/framework/views');

        return $app;
    }

    protected function tearDown(): void
    {
        $root = $this->root;

        parent::tearDown();

        (new Filesystem)->deleteDirectory($root);
    }

    /** @return list<string> */
    private function compiled(): array
    {
        return array_values(array_filter(glob($this->root.'/storage/framework/views/*') ?: [], fn ($f) => basename($f) !== '.gitignore'));
    }

    public function test_view_cache_compiles_every_blade_template_including_package_namespaces(): void
    {
        $this->artisan('view:cache')
            ->expectsOutputToContain('Blade templates cached successfully.')
            ->assertOk();

        // Each compiled file ends with the template it came from.
        $sources = array_map(fn ($f) => trim(preg_replace('#^.*/\*\*PATH(.*) ENDPATH\*\*/ \?>$#s', '$1', file_get_contents($f))), $this->compiled());

        foreach (glob($this->root.'/resources/views/{,*/}*.blade.php', GLOB_BRACE) as $template) {
            $this->assertContains(realpath($template), array_map('realpath', $sources));
        }

        $this->assertContains(realpath(dirname(__DIR__, 2).'/src/Mariner/Notifications/resources/views/email.blade.php'), array_map('realpath', $sources));
    }

    public function test_view_clear_removes_compiled_files_and_directories(): void
    {
        is_dir($views = $this->root.'/storage/framework/views') || mkdir($views, 0777, true);
        file_put_contents($views.'/filehash123.php', '<?php');
        mkdir($views.'/test_33');
        file_put_contents($views.'/test_33/inner.php', '<?php');

        $this->artisan('view:clear')
            ->expectsOutputToContain('Compiled views cleared successfully.')
            ->assertOk();

        $this->assertSame([], $this->compiled());
    }

    public function test_view_cache_clears_before_compiling(): void
    {
        is_dir($views = $this->root.'/storage/framework/views') || mkdir($views, 0777, true);
        file_put_contents($views.'/stale.php', '<?php');

        $this->artisan('view:cache')->assertOk();

        $this->assertFileDoesNotExist($views.'/stale.php');
    }

    public function test_view_clear_fails_without_a_compiled_path(): void
    {
        $this->app['config']->set('view.compiled', null);

        $this->assertThrows(fn () => $this->withoutMockingConsoleOutput()->artisan('view:clear'), \RuntimeException::class, 'View path not found.');
    }
}
