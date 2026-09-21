<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Subscriber;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\PendingEventStore;
use Shop4GoogleTagManager\Subscriber\PendingEventCookieSubscriber;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class PendingEventCookieSubscriberTest extends TestCase
{
    public function testSetsCookieWhenEventsAreQueued(): void
    {
        $response = $this->dispatch($this->request(), hasPending: true);

        $cookie = $this->findCookie($response);

        static::assertNotNull($cookie);
        static::assertSame('1', $cookie->getValue());
        // das storefront-js muss es lesen koennen
        static::assertFalse($cookie->isHttpOnly());
    }

    public function testClearsCookieWhenQueueIsEmpty(): void
    {
        $request = $this->request();
        $request->cookies->set(PendingEventStore::FLAG_COOKIE, '1');

        $cookie = $this->findCookie($this->dispatch($request, hasPending: false));

        static::assertNotNull($cookie);
        static::assertSame('', $cookie->getValue());
    }

    public function testDoesNothingWhenStateAlreadyMatches(): void
    {
        $request = $this->request();
        $request->cookies->set(PendingEventStore::FLAG_COOKIE, '1');

        static::assertNull($this->findCookie($this->dispatch($request, hasPending: true)));
        static::assertNull($this->findCookie($this->dispatch($this->request(), hasPending: false)));
    }

    public function testSkipsCacheableRoutes(): void
    {
        // ein Set-Cookie auf einer cachebaren Antwort wuerde das Flag im HTTP-Cache
        // landen lassen und damit an fremde Besucher ausgeliefert
        $request = $this->request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);

        static::assertNull($this->findCookie($this->dispatch($request, hasPending: true)));
    }

    public function testActsOnRoutesThatExplicitlyDisableTheCache(): void
    {
        // die eigenen endpunkte setzen _httpCache auf false - das ist keine cachebare route
        $request = $this->request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, false);

        static::assertNotNull($this->findCookie($this->dispatch($request, hasPending: true)));
    }

    public function testIgnoresSubRequests(): void
    {
        $subscriber = new PendingEventCookieSubscriber($this->store(true));
        $response = new Response();

        $subscriber->onResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $this->request(),
            HttpKernelInterface::SUB_REQUEST,
            $response,
        ));

        static::assertNull($this->findCookie($response));
    }

    private function dispatch(Request $request, bool $hasPending): Response
    {
        $subscriber = new PendingEventCookieSubscriber($this->store($hasPending));
        $response = new Response();

        $subscriber->onResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        return $response;
    }

    private function store(bool $hasPending): PendingEventStore
    {
        $store = $this->createMock(PendingEventStore::class);
        $store->method('hasPending')->willReturn($hasPending);

        return $store;
    }

    private function request(): Request
    {
        return Request::create('https://shop.example/account');
    }

    private function findCookie(Response $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === PendingEventStore::FLAG_COOKIE) {
                return $cookie;
            }
        }

        return null;
    }
}
