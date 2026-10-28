---
type: Module
title: Notifications
description: Notification channel manager and sender with mail, database and broadcast channels.
resource: src/Mariner/Notifications/ChannelManager.php
tags: [notifications, channels]
status: draft
generated: { by: claude-opus-5-5, at: 2026-10-27T00:00:00Z }
sources:
  - id: provider
    resource: src/Mariner/Notifications/NotificationServiceProvider.php
    title: NotificationServiceProvider
  - id: manager
    resource: src/Mariner/Notifications/ChannelManager.php
    title: ChannelManager
  - id: mailchannel
    resource: src/Mariner/Notifications/Channels/MailChannel.php
    title: MailChannel
  - id: facade
    resource: src/Mariner/NutsAndBolts/Facades/Notification.php
    title: Notification
  - id: email
    resource: src/Mariner/Notifications/resources/views/email.blade.php
    title: email.blade.php
---

# Overview

ChannelManager dispatches notifications through NotificationSender to channels (mail, database, broadcast); MailMessage builder; contracts in src/Mariner/Contracts/Notifications (Dispatcher, Factory).[^manager]

Provider binds `ChannelManager::class` via registerSingleton and aliases it to Mariner\Contracts\Notifications\Dispatcher and Factory.[^provider] Facade Notification, accessor `ChannelManager::class`.[^facade]

Voyager imports: Voyager\Broadcasting, Voyager\Bus, Voyager\Config, Voyager\Console, Voyager\Contracts\{Broadcasting,Bus,NutsAndBolts,Queue,Signals}, Voyager\Database\{Instrument,Migrations,Schema}, Voyager\NutsAndBolts (Collection, Concerns, DataObjects, Manager, ServiceProvider, Str), Voyager\Queue, Voyager\Vessel\ControlPanel.[^manager]

Config keys: `mail.markdown.theme` (MailChannel.php:142), `app.name` (email.blade.php:43).[^mailchannel][^email]

# Async

`ChannelManager::async(?pool)` returns `AsyncNotifications`: `send`/`sendNow`/`locale`, each send in a loop task answering with a Promise, over a copy of the manager whose database channel saves with the model's `saveAsync()` (`DatabaseChannel::via`; events here, SQL in a worker) and whose mail channel sends through a worker (`MailChannel::via`).

[^provider]: NotificationServiceProvider
[^manager]: ChannelManager
[^mailchannel]: MailChannel
[^facade]: Notification
[^email]: email.blade.php
