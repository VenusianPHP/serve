<?php

/*
 * Analysis only, never loaded: PHPStan skips a trait no class uses, and these traits are used by apps,
 * not by serve itself. Each class here hosts them the way an app would, so their code is analysed.
 */

namespace Mariner\PHPStan;

use Mariner\Auth\Access\AuthorizesRequests;
use Mariner\Core\Testing\WithoutMiddleware;
use Mariner\Http\Resources\TransformsToResource;
use Mariner\Notifications\HasDatabaseNotifications;
use Mariner\Notifications\Notifiable;
use Mariner\NutsAndBolts\Traits\ParsesSqlServerConfigurationUrls;
use Mariner\Routing\Concerns\BindsToRoutes;
use Mariner\Routing\Controller;
use Mariner\Routing\RouteDependencyResolverTrait;
use Mariner\Validation\ValidatesRequests;
use Voyager\Database\Instrument\Model;
use Voyager\Vessel\ControlPanel;

final class AppController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;
}

/**
 * @property string $email
 */
final class AppUser extends Model
{
    use Notifiable, TransformsToResource, BindsToRoutes;
}

final class AppNotifiable extends Model
{
    use HasDatabaseNotifications;
}

abstract class AppTest extends \PHPUnit\Framework\TestCase
{
    use WithoutMiddleware;
}

final class AppDependencyResolver
{
    use RouteDependencyResolverTrait;

    public function __construct(protected ControlPanel $container) {}
}

final class AppConnector
{
    use ParsesSqlServerConfigurationUrls;
}
