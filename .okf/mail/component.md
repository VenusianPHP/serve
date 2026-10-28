---
type: Module
title: Mail
description: Mailer, mail manager, mailables, markdown mail and Symfony-transport drivers.
resource: src/Mariner/Mail/MailManager.php
tags: [mail, transport, mailable]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: provider
    resource: src/Mariner/Mail/MailServiceProvider.php
    title: MailServiceProvider
  - id: manager
    resource: src/Mariner/Mail/MailManager.php
    title: MailManager
  - id: facade
    resource: src/Mariner/NutsAndBolts/Facades/Mail.php
    title: Mail
---

# Overview

Mail manager resolving named mailers onto transports (smtp, sendmail, mail, ses, ses-v2, resend, cloudflare, mailgun, postmark, log, array, failover, roundrobin); Mailable, PendingMail, Markdown rendering; contracts in src/Mariner/Contracts/Mail (Attachable, Factory, MailQueue, Mailable, Mailer).[^manager]

Provider binds `'mail.manager'` via registerSingleton, `'mailer'` via bind (resolves `mail.manager`->mailer()), and `Markdown::class` via registerSingleton; deferred, provides those three.[^provider] Facade Mail, accessor `'mail.manager'`.[^facade]

Voyager imports: Voyager\Bus, Voyager\Config, Voyager\Contracts\{Filesystem,NutsAndBolts,Queue,Signals}, Voyager\Log, Voyager\NutsAndBolts (Collection, Concerns, ConfigurationUrlParser, DataObjects, HtmlString, ServiceProvider, Str), Voyager\Queue, Voyager\Vessel\ControlPanel.[^manager]

Config keys: `mail.default`, `mail.driver`, `mail.mailers.{name}`, `mail.from`/`mail.reply_to`/`mail.to`/`mail.return_path` (via `'mail.'.$type`), `mail.sendmail`, `mail.log_channel`, `mail.markdown.theme`, `mail.markdown.paths`, `mail.markdown.extensions`, `services.ses`, `services.resend.key`, `services.cloudflare.*`, `services.mailgun`, `services.postmark.*`.[^manager][^provider]

# Async

`Mailer::via(?pool)` (`969fc58`) returns `AsyncMailer`: `send`/`sendNow` each answer with a Promise from a loop task. The message is built and `MessageSending`/`MessageSent` fire in this process; the Symfony `Email` + `Envelope` go to a worker (`DeliverMessage`), which rebuilds the transport from the mailer's config (`MailManager::build()` records it) and sends. Attachments cross intact, streams included (read in on serialize). Refused clearly: a mailer given a transport object, and the array transport. Notifications' `ChannelManager::async(?pool)` pools the mail channel through `PooledMailers`. Thread-pool cases run wherever ext-parallel is loaded (the fixture app enables thread workers there) and skip elsewhere; CI has no ZTS job. Verified locally under ZTS PHP 8.4.27 + ext-parallel 1.2.15; swoole must not be loaded alongside parallel, or parallel segfaults.

# Open items

- Laravel wording: MailManager.php:464, MailManager.php:546, MailManager.php:569, Message.php:31.

[^provider]: MailServiceProvider
[^manager]: MailManager
[^facade]: Mail
