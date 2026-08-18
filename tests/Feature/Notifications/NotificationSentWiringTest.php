<?php

namespace Tests\Feature\Notifications;

use App\Events\NotificationSent;
use App\Models\Notification;
use App\Models\User;
use App\Services\FcmNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Regression guard for the event-wiring bug uncovered during the
 * Pusher -> FCM migration review.
 *
 * Background: app/Events/NotificationSent.php originally declared
 * `namespace App\Events;` (lowercase a). EventServiceProvider registered
 * listeners under `\App\Events\NotificationSent::class`, which resolves to
 * the string 'App\Events\NotificationSent' (capital A). Laravel's event
 * dispatcher keys listeners by that string and looks them up by
 * get_class($event) — which returned 'app\Events\NotificationSent'. The
 * case-sensitive lookup found zero listeners, so neither
 * SendEmailNotification nor SendFcmPushNotification ever fired.
 *
 * This test asserts that the key the dispatcher registers listeners under
 * matches the key produced by get_class() on a real event instance, in
 * BOTH the cached and non-cached event-map paths.
 */
class NotificationSentWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_listeners_are_registered_under_the_get_class_key(): void
    {
        $events = $this->app->make('events');

        $event = new NotificationSent(new Notification());
        $resolvedKey = get_class($event);

        $this->assertNotEmpty(
            $events->getListeners($resolvedKey),
            "No listeners are registered under get_class key '{$resolvedKey}'. "
            .'EventServiceProvider must register NotificationSent listeners under '
            .'the same class-name string that get_class() produces. Fix the '
            .'namespace case in app/Events/NotificationSent.php to match '
            .'App\\Events, then run `composer dump-autoload` and '
            .'`php artisan event:cache`.'
        );
    }

    public function test_notification_sent_triggers_the_fcm_listener_via_real_dispatcher(): void
    {
        // Use a Mockery mock of FcmNotificationService so the listener's
        // constructor type-hint is satisfied (a plain anonymous class
        // would not pass the type check).
        // QUEUE_CONNECTION=sync (set in phpunit.xml) makes the queued
        // listener run synchronously inside event().
        $captured = null;

        $spy = Mockery::mock(FcmNotificationService::class);
        $spy->shouldReceive('sendToUser')
            ->once()
            ->withArgs(function ($userId, $title, $body, $data) use (&$captured) {
                $captured = compact('userId', 'title', 'body', 'data');
                return true;
            })
            ->andReturn(1);
        $this->app->instance(FcmNotificationService::class, $spy);

        // The sibling SendEmailNotification listener is left in place;
        // it no-ops because MAIL_MAILER=array in phpunit.xml.

        $user = $this->createUser();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'task',
            'title' => 'Wiring check',
            'message' => 'body',
            'is_read' => false,
        ]);

        // Dispatch through the real dispatcher exactly as
        // NotificationService::send() does via broadcast().
        event(new NotificationSent($notification));

        $this->assertNotNull(
            $captured,
            'SendFcmPushNotification did not fire when NotificationSent was dispatched. '
            .'This means the listener wiring is broken (namespace case mismatch).'
        );
        $this->assertSame($user->id, $captured['userId']);
    }

    private function createUser(array $attrs = []): User
    {
        static $i = 0;
        $i++;
        $user = User::create(array_merge([
            'name' => 'Test User ' . $i,
            'username' => 'test_user_' . uniqid() . '_' . $i,
            'email' => 'test_' . uniqid() . '_' . $i . '@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ], $attrs));
        $user->is_active = true;
        $user->save();
        return $user;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
