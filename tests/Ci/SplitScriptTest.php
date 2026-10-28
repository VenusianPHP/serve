<?php

/*
 * .github/split.sh against scratch repositories: a synthetic monorepo with one component, its origin
 * and the component's repository are bare repos on disk, and splitsh-lite is a stand-in built on
 * `git subtree split`. Nothing touches the network.
 */

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/serve-split-'.bin2hex(random_bytes(4));
    mkdir($this->dir.'/bin', 0777, true);

    // splitsh-lite --prefix=<dir> --origin=<ref> --quiet  →  the split commit's SHA
    file_put_contents($this->dir.'/bin/splitsh-lite', <<<'SH'
#!/usr/bin/env bash
for arg in "$@"; do
    case $arg in
        --prefix=*) prefix=${arg#--prefix=} ;;
        --origin=*) origin=${arg#--origin=} ;;
    esac
done
git subtree split -q --prefix="$prefix" "$origin"
SH);
    chmod($this->dir.'/bin/splitsh-lite', 0755);

    splitGit($this, 'init --quiet --bare origin.git');
    splitGit($this, 'init --quiet --bare remotes/widget.git');
    splitGit($this, 'init --quiet -b main mono');
    splitGit($this, 'remote add origin '.$this->dir.'/origin.git', 'mono');

    splitCommitMono($this, '0.11.0', 'first');
});

afterEach(fn () => exec('rm -rf '.escapeshellarg($this->dir)));

/** Run git in a directory under the scratch root; returns trimmed stdout, failing the test on a non-zero exit. */
function splitGit($test, string $args, string $in = ''): string
{
    $cwd = $test->dir.($in === '' ? '' : '/'.$in);
    exec('cd '.escapeshellarg($cwd).' && git -c user.name=t -c user.email=t@t '.$args.' 2>&1', $out, $status);
    expect($status)->toBe(0, "git {$args}: ".implode("\n", $out));

    return trim(implode("\n", $out));
}

/** Commit the monorepo at a version: root and component manifests that agree with it, plus a changing file. */
function splitCommitMono($test, string $version, string $content): void
{
    $line = implode('.', array_slice(explode('.', $version), 0, 2));
    $alias = ['branch-alias' => ['dev-main' => "{$line}.x-dev"]];
    is_dir($component = $test->dir.'/mono/src/Mariner/Widget') || mkdir($component, 0777, true);
    file_put_contents($test->dir.'/mono/composer.json', json_encode(['name' => 'venusian/serve', 'version' => $version, 'extra' => $alias]));
    file_put_contents($test->dir.'/mono/src/Mariner/Widget/composer.json', json_encode(['name' => 'venusian-mariner/widget', 'extra' => $alias]));
    file_put_contents($test->dir.'/mono/src/Mariner/Widget/Widget.php', "<?php // {$content}\n");
    splitGit($test, 'add -A', 'mono');
    splitGit($test, "commit --quiet -m {$content}", 'mono');
    splitGit($test, "tag v{$version}", 'mono');
    splitGit($test, 'push --quiet origin main --tags', 'mono');
}

/** Give the component repository a branch holding one unrelated commit, as `Init Commit` does. */
function splitSeedComponent($test, string $branch): string
{
    $seed = $test->dir.'/seed-'.$branch;
    exec('git init --quiet -b '.escapeshellarg($branch).' '.escapeshellarg($seed));
    file_put_contents($seed.'/LICENSE', 'init');
    exec('cd '.escapeshellarg($seed).' && git add -A && git -c user.name=t -c user.email=t@t commit --quiet -m Init && git push --quiet '.escapeshellarg($test->dir.'/remotes/widget.git').' '.escapeshellarg($branch).' 2>&1');

    return trim(shell_exec('cd '.escapeshellarg($seed).' && git rev-parse HEAD'));
}

/** Run split.sh for a tag; returns [exit status, output]. */
function runSplit($test, string $tag): array
{
    $cmd = sprintf(
        'cd %s && PATH=%s:$PATH SPLIT_REMOTE=%s bash %s %s 2>&1',
        escapeshellarg($test->dir.'/mono'),
        escapeshellarg($test->dir.'/bin'),
        escapeshellarg('file://'.$test->dir.'/remotes'),
        escapeshellarg(dirname(__DIR__, 2).'/.github/split.sh'),
        escapeshellarg($tag)
    );
    exec($cmd, $out, $status);

    return [$status, implode("\n", $out)];
}

function componentHead($test, string $branch): string
{
    return trim((string) shell_exec('git --git-dir='.escapeshellarg($test->dir.'/remotes/widget.git').' rev-parse --verify --quiet refs/heads/'.$branch));
}

it('replaces an unrelated init commit on the first publish, and creates a missing branch', function () {
    splitSeedComponent($this, 'main');           // main holds only an init commit; 0.11.x does not exist
    $legacy = splitSeedComponent($this, '0.8.x');

    [$status, $output] = runSplit($this, 'v0.11.0');

    $split = trim(shell_exec('cd '.escapeshellarg($this->dir.'/mono').' && git subtree split -q --prefix=src/Mariner/Widget v0.11.0'));

    expect($status)->toBe(0, $output)
        ->and($output)->toContain('-> 0.11.x (create)')->toContain('-> main (replace)')
        ->and(componentHead($this, '0.11.x'))->toBe($split)
        ->and(componentHead($this, 'main'))->toBe($split)
        ->and(componentHead($this, '0.8.x'))->toBe($legacy)
        ->and(trim(shell_exec('git --git-dir='.escapeshellarg($this->dir.'/remotes/widget.git').' rev-parse v0.11.0^{commit}')))->toBe($split);
});

it('fast-forwards on the next tag, without forcing', function () {
    runSplit($this, 'v0.11.0');
    $first = componentHead($this, '0.11.x');

    splitCommitMono($this, '0.11.1', 'second');
    [$status, $output] = runSplit($this, 'v0.11.1');

    expect($status)->toBe(0, $output)
        ->and($output)->toContain('-> 0.11.x (fast-forward)')->not->toContain('(replace)')
        ->and(trim(shell_exec('git --git-dir='.escapeshellarg($this->dir.'/remotes/widget.git').' merge-base --is-ancestor '.$first.' 0.11.x && echo yes')))->toBe('yes');
});

it('refuses to rewrite a branch that holds commits the split does not, and pushes nothing', function () {
    runSplit($this, 'v0.11.0');

    // Someone commits straight to the component's 0.11.x.
    $work = $this->dir.'/stray';
    exec('git clone --quiet -b 0.11.x '.escapeshellarg($this->dir.'/remotes/widget.git').' '.escapeshellarg($work).' 2>&1');
    file_put_contents($work.'/Stray.php', '<?php');
    exec('cd '.escapeshellarg($work).' && git add -A && git -c user.name=t -c user.email=t@t commit --quiet -m stray && git push --quiet origin 0.11.x 2>&1');
    $stray = componentHead($this, '0.11.x');
    $main = componentHead($this, 'main');

    splitCommitMono($this, '0.11.1', 'second');
    [$status, $output] = runSplit($this, 'v0.11.1');

    expect($status)->not->toBe(0)
        ->and($output)->toContain('refusing to rewrite published history')
        ->and(componentHead($this, '0.11.x'))->toBe($stray)
        ->and(componentHead($this, 'main'))->toBe($main)
        ->and(trim((string) shell_exec('git --git-dir='.escapeshellarg($this->dir.'/remotes/widget.git').' tag -l v0.11.1')))->toBe('');
});

it('refuses to replace an unrelated branch that holds more than one commit, and pushes nothing', function () {
    $seed = splitSeedComponent($this, 'main');
    $work = $this->dir.'/seed-main';
    file_put_contents($work.'/README', 'someone else');
    exec('cd '.escapeshellarg($work).' && git add -A && git -c user.name=t -c user.email=t@t commit --quiet -m work && git push --quiet '.escapeshellarg($this->dir.'/remotes/widget.git').' main 2>&1');
    $main = componentHead($this, 'main');

    [$status, $output] = runSplit($this, 'v0.11.0');

    expect($main)->not->toBe($seed)
        ->and($status)->not->toBe(0)
        ->and($output)->toContain('refusing to rewrite published history')
        ->and(componentHead($this, 'main'))->toBe($main)
        ->and(componentHead($this, '0.11.x'))->toBe('')
        ->and(trim((string) shell_exec('git --git-dir='.escapeshellarg($this->dir.'/remotes/widget.git').' tag -l v0.11.0')))->toBe('');
});

