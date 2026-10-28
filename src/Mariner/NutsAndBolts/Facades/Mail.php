<?php

namespace Mariner\NutsAndBolts\Facades;

use Mariner\Mail\Testing\MailFake;

/**
 * @method static \Mariner\Contracts\Mail\Mailer mailer(\UnitEnum|string|null $name = null)
 * @method static \Mariner\Mail\Mailer driver(\UnitEnum|string|null $driver = null)
 * @method static \Mariner\Mail\Mailer build(array $config)
 * @method static \Symfony\Component\Mailer\Transport\TransportInterface createSymfonyTransport(array $config)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(\UnitEnum|string $name)
 * @method static void purge(\UnitEnum|string|null $name = null)
 * @method static \Mariner\Mail\MailManager extend(string $driver, \Closure $callback)
 * @method static \Voyager\Contracts\Core\FrameworkCore getApplication()
 * @method static \Mariner\Mail\MailManager setApplication(\Voyager\Contracts\Core\FrameworkCore $app)
 * @method static \Mariner\Mail\MailManager forgetMailers()
 * @method static void alwaysFrom(string $address, string|null $name = null)
 * @method static void alwaysReplyTo(string $address, string|null $name = null)
 * @method static void alwaysReturnPath(string $address)
 * @method static void alwaysTo(string $address, string|null $name = null)
 * @method static \Mariner\Mail\PendingMail to(mixed $users, string|null $name = null)
 * @method static \Mariner\Mail\PendingMail cc(mixed $users, string|null $name = null)
 * @method static \Mariner\Mail\PendingMail bcc(mixed $users, string|null $name = null)
 * @method static \Mariner\Mail\SentMessage|null html(string $html, mixed $callback)
 * @method static \Mariner\Mail\SentMessage|null raw(string $text, mixed $callback)
 * @method static \Mariner\Mail\SentMessage|null plain(string $view, array $data, mixed $callback)
 * @method static string render(string|array $view, array $data = [])
 * @method static \Mariner\Mail\SentMessage|null send(\Mariner\Contracts\Mail\Mailable|string|array $view, array $data = [], \Closure|string|null $callback = null)
 * @method static \Mariner\Mail\SentMessage|null sendNow(\Mariner\Contracts\Mail\Mailable|string|array $mailable, array $data = [], \Closure|string|null $callback = null)
 * @method static mixed queue(\Mariner\Contracts\Mail\Mailable $view, \BackedEnum|string|null $queue = null)
 * @method static mixed onQueue(\BackedEnum|string|null $queue, \Mariner\Contracts\Mail\Mailable $view)
 * @method static mixed queueOn(string $queue, \Mariner\Contracts\Mail\Mailable $view)
 * @method static mixed later(\DateTimeInterface|\DateInterval|int $delay, \Mariner\Contracts\Mail\Mailable $view, string|null $queue = null)
 * @method static mixed laterOn(string $queue, \DateTimeInterface|\DateInterval|int $delay, \Mariner\Contracts\Mail\Mailable $view)
 * @method static \Symfony\Component\Mailer\Transport\TransportInterface getSymfonyTransport()
 * @method static \Mariner\Contracts\View\Factory getViewFactory()
 * @method static void setSymfonyTransport(\Symfony\Component\Mailer\Transport\TransportInterface $transport)
 * @method static \Mariner\Mail\Mailer setQueue(\Voyager\Contracts\Queue\Factory $queue)
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static void assertSent(string|\Closure $mailable, callable|array|string|int|null $callback = null)
 * @method static void assertSentTimes(string $mailable, int $times = 1)
 * @method static void assertSentOnce(string $mailable)
 * @method static void assertNotOutgoing(string|\Closure $mailable, callable|null $callback = null)
 * @method static void assertNotSent(string|\Closure $mailable, callable|array|string|null $callback = null)
 * @method static void assertNothingOutgoing()
 * @method static void assertNothingSent()
 * @method static void assertQueued(string|\Closure $mailable, callable|array|string|int|null $callback = null)
 * @method static void assertQueuedTimes(string $mailable, int $times = 1)
 * @method static void assertQueuedOnce(string $mailable)
 * @method static void assertNotQueued(string|\Closure $mailable, callable|array|string|null $callback = null)
 * @method static void assertNothingQueued()
 * @method static void assertSentCount(int $count)
 * @method static void assertQueuedCount(int $count)
 * @method static void assertOutgoingCount(int $count)
 * @method static \Voyager\NutsAndBolts\Collection sent(string|\Closure $mailable, callable|null $callback = null)
 * @method static bool hasSent(string $mailable)
 * @method static \Voyager\NutsAndBolts\Collection queued(string|\Closure $mailable, callable|null $callback = null)
 * @method static bool hasQueued(string $mailable)
 *
 * @see \Mariner\Mail\MailManager
 * @see \Mariner\Mail\Testing\MailFake
 */
class Mail extends Facade
{
    /**
     * Replace the bound instance with a fake.
     *
     * @return \Mariner\Mail\Testing\MailFake
     */
    public static function fake()
    {
        $actualMailManager = static::isFake()
            ? static::getFacadeRoot()->manager
            : static::getFacadeRoot();

        return tap(new MailFake($actualMailManager), function ($fake) {
            static::swap($fake);
        });
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mail.manager';
    }
}
