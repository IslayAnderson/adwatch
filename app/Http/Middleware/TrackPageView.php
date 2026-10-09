<?php

namespace App\Http\Middleware;

use App\Services\Analytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sends a GA4 page_view for every successful HTML page, and gives consenting new visitors a client id cookie. */
class TrackPageView
{
    public function __construct(private Analytics $analytics) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Make sure the client id is stable from the very first hit.
        if ($this->analytics->consented($request) && ! $request->cookie('_ga') && ! $request->cookie(Analytics::CLIENT_COOKIE)) {
            $request->cookies->set(Analytics::CLIENT_COOKIE, Analytics::newClientId());
            $setCookie = true;
        }

        $response = $next($request);

        if (! empty($setCookie)) {
            $response->headers->setCookie(cookie(Analytics::CLIENT_COOKIE, $request->cookie(Analytics::CLIENT_COOKIE), 60 * 24 * 395));
        }

        if ($request->isMethod('GET') && ! $request->expectsJson() && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            preg_match('~<title>(.*?)</title>~s', (string) $response->getContent(), $title);
            $this->analytics->event('page_view', [
                'page_location' => $request->fullUrl(),
                'page_referrer' => $request->headers->get('referer'),
                'page_title' => isset($title[1]) ? html_entity_decode(trim($title[1])) : null,
            ]);
        }

        return $response;
    }
}
