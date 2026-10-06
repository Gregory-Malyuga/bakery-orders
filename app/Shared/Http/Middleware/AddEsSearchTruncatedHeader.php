<?php

namespace Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shared\Elasticsearch\EsTruncationBag;
use Symfony\Component\HttpFoundation\Response;

class AddEsSearchTruncatedHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $bag = app(EsTruncationBag::class);

        if ($bag->isTruncated()) {
            $response->headers->set('X-Search-Truncated', 'true');
        }

        return $response;
    }
}
